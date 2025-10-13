#!/bin/bash
set -e

# --- ATTACH IMAGES TO PRODUCTS USING CSV ---
if [ ! -f app/etc/env.php ]; then
  echo "ERROR: app/etc/env.php not found. Run from Magento root."
  exit 1
fi

# extract DB creds (will be evaluated when this script runs)
read -r DB_HOST DB_NAME DB_USER DB_PASS <<CRED
$(php -r '$c = include "app/etc/env.php"; $d = $c["db"]["connection"]["default"]; echo $d["host"]."\n".$d["dbname"]."\n".$d["username"]."\n".$d["password"]."\n";')
CRED

MYSQL_CMD="mysql -u${DB_USER} -p'${DB_PASS}' -h ${DB_HOST} ${DB_NAME} -N -s"

echo "Using DB host=\${DB_HOST} db=\${DB_NAME} user=\${DB_USER}"

# choose CSV (auto-preferred)
CSV=/tmp/image_to_sku_auto.csv
if [ ! -f "$CSV" ]; then
  CSV=/tmp/image_to_sku.csv
fi
if [ ! -f "$CSV" ]; then
  echo "ERROR: No CSV found. Create /tmp/image_to_sku_auto.csv or /tmp/image_to_sku.csv with lines 'filename,SKU'"
  exit 1
fi
echo "Using CSV: $CSV"

# get media_gallery attribute_id
ATTR_ID=$(echo "SELECT attribute_id FROM eav_attribute WHERE attribute_code='media_gallery' AND entity_type_id=(SELECT entity_type_id FROM eav_entity_type WHERE entity_type_code='catalog_product') LIMIT 1;")
if [ -z "$ATTR_ID" ]; then
  echo "ERROR: could not find media_gallery attribute_id"
  exit 1
fi
echo "media_gallery attribute_id = $ATTR_ID"

pos=1

while IFS=, read -r filename sku || [ -n "$filename" ]; do
  filename=$(echo "$filename" | xargs)
  sku=$(echo "$sku" | xargs)

  [ -z "$filename" ] && continue
  [ -z "$sku" ] && { echo "Skipping $filename — empty SKU"; continue; }

  ffound=$(find pub/media/catalog/product -type f -iname "$filename" -print -quit 2>/dev/null)
  if [ -z "$ffound" ]; then
    echo "File not found on disk for '$filename' — skipping."
    continue
  fi

  FULL_VALUE="${ffound#/var/www/magento2/pub/media/catalog/product}"

  value_id=$(echo "SELECT value_id FROM mage_catalog_product_entity_media_gallery WHERE value='${FULL_VALUE}' LIMIT 1;" | tr -d '\r\n')
  if [ -z "$value_id" ]; then
    echo "INSERT INTO mage_catalog_product_entity_media_gallery (attribute_id, value) VALUES (${ATTR_ID}, '${FULL_VALUE}');"
    value_id=$(echo "SELECT LAST_INSERT_ID();")
    echo "Inserted gallery value_id=${value_id} for ${FULL_VALUE}"
  else
    echo "Gallery row exists: value_id=${value_id} for ${FULL_VALUE}"
  fi
  entity_id=$(echo "SELECT entity_id FROM mage_catalog_product_entity WHERE sku='${sku}' LIMIT 1;" | tr -d '\r\n')
  if [ -z "${entity_id}" ]; then
    echo "SKU not found in DB: '${sku}' — skipping link for ${filename}"
    pos=$((pos+1))
    continue
  fi

  val_exists=$(echo "SELECT value_id FROM mage_catalog_product_entity_media_gallery_value WHERE value_id='${value_id}' AND store_id=0 LIMIT 1;" | tr -d '\r\n')
  if [ -z "${val_exists}" ]; then
    echo "INSERT INTO mage_catalog_product_entity_media_gallery_value (value_id, store_id, label, position, disabled) VALUES (${value_id}, 0, '', ${pos}, 0);"
    echo "Inserted gallery value (value_id=${value_id}, store=0, pos=${pos})"
  else
    echo "Gallery value already exists for value_id=${value_id} store=0"
  fi

  rel_exists=$(echo "SELECT value_id FROM mage_catalog_product_entity_media_gallery_value_to_entity WHERE value_id='${value_id}' AND entity_id='${entity_id}' LIMIT 1;" | tr -d '\r\n')
  if [ -z "${rel_exists}" ]; then
    echo "INSERT INTO mage_catalog_product_entity_media_gallery_value_to_entity (value_id, entity_id) VALUES (${value_id}, ${entity_id});"
    echo "Linked value_id=${value_id} -> entity_id=${entity_id} (sku=${sku})"
  else
    echo "Link exists for value_id=${value_id} -> entity_id=${entity_id}"
  fi

  pos=$((pos+1))
done < "$CSV"

echo "Rebuilding resized images..."
php -d memory_limit=2G bin/magento catalog:images:resize || true

echo "Reindexing..."
php bin/magento indexer:reindex

echo "Flushing cache..."
php bin/magento cache:flush

echo "Done. Check products in Admin (Catalog → Products) for images and verify frontend pages."
