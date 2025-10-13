#!/bin/bash
set -euo pipefail

# === ATTACH IMAGES TO PRODUCTS USING CSV ===
# Usage: ./attach_images.sh   (default: dry-run)
#        DRY_RUN=0 ./attach_images.sh  (perform DB writes)
DRY_RUN="${DRY_RUN:-1}"

# ensure we're in Magento root
if [ ! -f app/etc/env.php ]; then
  echo "ERROR: app/etc/env.php not found. Run from Magento root."
  exit 1
fi

# Extract DB creds from app/etc/env.php using PHP and export as shell vars.
# Using printf with quoted values so shell variable assignment is safe.
eval "$(php -r '
  $c = include "app/etc/env.php";
  $d = $c["db"]["connection"]["default"];
  $host = isset($d["host"])?$d["host"]:"localhost";
  $name = isset($d["dbname"])?$d["dbname"]:"";
  $user = isset($d["username"])?$d["username"]:"";
  $pass = isset($d["password"])?$d["password"]:"";
  // Print shell-safe assignments
  printf("DB_HOST=%s\nDB_NAME=%s\nDB_USER=%s\nDB_PASS=%s\n",
    addcslashes($host, \"\\'\\\"\"),
    addcslashes($name, \"\\'\\\"\"),
    addcslashes($user, \"\\'\\\"\"),
    addcslashes($pass, \"\\'\\\"\")
  );
')"

# Basic sanity
if [ -z "${DB_USER:-}" ] || [ -z "${DB_NAME:-}" ]; then
  echo "ERROR: failed to read DB credentials from app/etc/env.php"
  exit 1
fi

echo "Using DB host=${DB_HOST} db=${DB_NAME} user=${DB_USER}"
# Build mysql command as array to handle special chars safely
MYSQL_CMD=(mysql -u"${DB_USER}" -p"${DB_PASS}" -h "${DB_HOST}" "${DB_NAME}" -N -s)

# Test DB connection
if ! "${MYSQL_CMD[@]}" -e "SELECT 1" >/dev/null 2>&1; then
  echo "ERROR: cannot connect to MySQL with credentials from env.php"
  exit 1
fi

# Choose CSV
CSV_AUTO=/tmp/image_to_sku_auto.csv
CSV_FALLBACK=/tmp/image_to_sku.csv
if [ -f "$CSV_AUTO" ]; then
  CSV="$CSV_AUTO"
elif [ -f "$CSV_FALLBACK" ]; then
  CSV="$CSV_FALLBACK"
else
  echo "ERROR: no CSV found. Create $CSV_AUTO or $CSV_FALLBACK with lines 'filename,SKU'"
  exit 1
fi
echo "Using CSV: $CSV"

# Get media_gallery attribute id
ATTR_ID="$("${MYSQL_CMD[@]}" -e "SELECT attribute_id FROM eav_attribute WHERE attribute_code='media_gallery' AND entity_type_id=(SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code='catalog_product') LIMIT 1;")"
if [ -z "$ATTR_ID" ]; then
  echo "ERROR: could not find media_gallery attribute_id"
  exit 1
fi
echo "media_gallery attribute_id = $ATTR_ID"

pos=1
while IFS=, read -r filename sku || [ -n "$filename" ]; do
  filename="$(echo "$filename" | xargs)"
  sku="$(echo "$sku" | xargs)"

  [ -z "$filename" ] && continue
  [ -z "$sku" ] && { echo "Skipping $filename — empty SKU"; continue; }

  # find file under pub/media/catalog/product (case-insensitive)
  ffound="$(find pub/media/catalog/product -type f -iname "$filename" -print -quit 2>/dev/null || true)"
  if [ -z "$ffound" ]; then
    echo "File not found on disk for '$filename' — skipping."
    continue
  fi

  # DB value path should be relative to /pub/media/catalog/product
  FULL_VALUE="${ffound#/var/www/magento2/pub/media/catalog/product}"

  # check if gallery entry exists
  value_id="$("${MYSQL_CMD[@]}" -e "SELECT value_id FROM mage_catalog_product_entity_media_gallery WHERE value='${FULL_VALUE}' LIMIT 1;" | tr -d '\r\n' || true)"
  if [ -z "$value_id" ]; then
    echo "[SQL] INSERT INTO mage_catalog_product_entity_media_gallery (attribute_id, value) VALUES (${ATTR_ID}, '${FULL_VALUE}');"
    if [ "$DRY_RUN" -eq 0 ]; then
      "${MYSQL_CMD[@]}" -e "INSERT INTO mage_catalog_product_entity_media_gallery (attribute_id, value) VALUES (${ATTR_ID}, '${FULL_VALUE}');"
      value_id="$("${MYSQL_CMD[@]}" -e "SELECT LAST_INSERT_ID();" | tr -d '\r\n')"
      echo "Inserted gallery value_id=${value_id} for ${FULL_VALUE}"
    fi
  else
    echo "Gallery row exists: value_id=${value_id} for ${FULL_VALUE}"
  fi

  # get product entity_id by sku
  entity_id="$("${MYSQL_CMD[@]}" -e "SELECT entity_id FROM mage_catalog_product_entity WHERE sku='${sku}' LIMIT 1;" | tr -d '\r\n' || true)"
  if [ -z "${entity_id}" ]; then
    echo "SKU not found in DB: '${sku}' — skipping link for ${filename}"
    pos=$((pos+1))
    continue
  fi

  # insert into mage_catalog_product_entity_media_gallery_value if missing (store 0)
  val_exists="$("${MYSQL_CMD[@]}" -e "SELECT value_id FROM mage_catalog_product_entity_media_gallery_value WHERE value_id='${value_id}' AND store_id=0 LIMIT 1;" | tr -d '\r\n' || true)"
  if [ -z "${val_exists}" ]; then
    echo "[SQL] INSERT INTO mage_catalog_product_entity_media_gallery_value (value_id, store_id, label, position, disabled) VALUES (${value_id}, 0, '', ${pos}, 0);"
    if [ "$DRY_RUN" -eq 0 ]; then
      "${MYSQL_CMD[@]}" -e "INSERT INTO mage_catalog_product_entity_media_gallery_value (value_id, store_id, label, position, disabled) VALUES (${value_id}, 0, '', ${pos}, 0);"
      echo "Inserted gallery value (value_id=${value_id}, store=0, pos=${pos})"
    fi
  else
    echo "Gallery value already exists for value_id=${value_id} store=0"
  fi

  # link to product entity (value_to_entity) if missing
  rel_exists="$("${MYSQL_CMD[@]}" -e "SELECT value_id FROM mage_catalog_product_entity_media_gallery_value_to_entity WHERE value_id='${value_id}' AND entity_id='${entity_id}' LIMIT 1;" | tr -d '\r\n' || true)"
  if [ -z "${rel_exists}" ]; then
    echo "[SQL] INSERT INTO mage_catalog_product_entity_media_gallery_value_to_entity (value_id, entity_id) VALUES (${value_id}, ${entity_id});"
    if [ "$DRY_RUN" -eq 0 ]; then
      "${MYSQL_CMD[@]}" -e "INSERT INTO mage_catalog_product_entity_media_gallery_value_to_entity (value_id, entity_id) VALUES (${value_id}, ${entity_id});"
      echo "Linked value_id=${value_id} -> entity_id=${entity_id} (sku=${sku})"
    fi
  else
    echo "Link exists for value_id=${value_id} -> entity_id=${entity_id}"
  fi

  pos=$((pos+1))
done < "$CSV"

echo "Rebuilding resized images (catalog:images:resize)..."
if [ "$DRY_RUN" -eq 0 ]; then
  php -d memory_limit=2G bin/magento catalog:images:resize || true
  php bin/magento indexer:reindex
  php bin/magento cache:flush
else
  echo "[DRY RUN] Would run: php -d memory_limit=2G bin/magento catalog:images:resize"
  echo "[DRY RUN] Would run: php bin/magento indexer:reindex"
  echo "[DRY RUN] Would run: php bin/magento cache:flush"
fi

echo "Done."
