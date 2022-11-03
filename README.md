# QRCode Generator

Usage:

```php
    $qrCode = new QRCode\Generator("Hello World!");
    $renderer = new QRCode\Renderer\Png();

    $imageData = $renderer->render($qrCode->getBitmap(), /* 300x300 */ 300);

    file_put_contents('qr-code.png', $imageData);
```
