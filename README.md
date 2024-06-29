AWS Core for the XP Framework
========================================================================

[![Build status on GitHub](https://github.com/xp-forge/aws/workflows/Tests/badge.svg)](https://github.com/xp-forge/aws/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/aws/version.png)](https://packagist.org/packages/xp-forge/aws)

Provides common AWS functionality in a low-level and therefore lightweight library (*less than 1% of the size of the official PHP SDK!*)

Invoking a lambda
-----------------

```php
use com\amazon\aws\{Credentials, ServiceEndpoint};
use util\Secret;
use util\cmd\Console;
use util\log\Logging;

$credentials= new Credentials($accessKey, new Secret($secretKey));

$api= (new ServiceEndpoint('lambda', $credentials))->in('eu-central-1')->version('2015-03-31');
$api->setTrace(Logging::all()->toConsole());

$r= $api->resource('/functions/greet/invocations')->transmit(['name' => getenv('USER')]);

Console::writeLine($r);
Console::writeLine($r->value());
```

Sharing a S3 resource
---------------------

```php
use com\amazon\aws\{ServiceEndpoint, Credentials};
use util\cmd\Console;

$api= new ServiceEndpoint('s3', new Credentials(
  getenv('AWS_ACCESS_KEY_ID'),
  getenv('AWS_SECRET_ACCESS_KEY'),
  getenv('AWS_SESSION_TOKEN')
));
$link= $api->in('eu-central-1')->using('my-bucket')->sign('/path/to/resource.png', timeout: 180);

Console::writeLine($link);
```

Streaming uploads to S3
-----------------------

```php
use com\amazon\aws\api\SignatureV4;
use com\amazon\aws\{ServiceEndpoint, Credentials};
use io\File;
use util\cmd\Console;

$credentials= new Credentials(/* ... */);

$file= new File('large.txt');
$file->open(File::READ);

try {
  $s3= (new ServiceEndpoint('s3', $credentials))->in('eu-central-1')->using('my-bucket');
  $transfer= $s3->open('PUT', 'target/'.$file->filename, [
    'x-amz-content-sha256' => SignatureV4::UNSIGNED, // Or calculate from file
    'Content-Type'         => 'text/plain',
    'Content-Length'       => $file->size(),
  ]);
  while (!$file->eof()) {
    $transfer->write($file->read());
  }
  $response= $transfer->finish();

  Console::writeLine($response);
} finally {
  $file->close();
}
```

See also
--------
* [AWS Lambda for XP Framework](https://github.com/xp-forge/lambda)
* [AWS Lambda Webservices for the XP Framework](https://github.com/xp-forge/lambda-ws)
