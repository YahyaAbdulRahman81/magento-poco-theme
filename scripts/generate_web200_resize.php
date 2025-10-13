<?php
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../app/bootstrap.php';
$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$obj = $bootstrap->getObjectManager();
$state = $obj->get('Magento\Framework\App\State');
try {
    $state->setAreaCode('frontend'); // needed for helpers
} catch (\Magento\Framework\Exception\LocalizedException $e) {
    // ignore if area code was already set
}

/** @var \Web200\ImageResize\Helper\ImageResize $helper */
$helper = $obj->get(\Web200\ImageResize\Helper\ImageResize::class);

// Example product image (already in pub/media/catalog/product/)
$image = "catalog/product/i/s/istockphoto-656678076-1024x1024_1.jpg";
$width = 300;
$height = 300;

try {
    $url = $helper->getResize()->resizeAndGetUrl(
        $image,
        $width,
        $height,
        [
            'keepAspectRatio' => true,
            'keepTransparency' => true,
            'keepFrame' => false,
            'quality' => 80
        ]
    );
    echo "Resized URL: $url\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
