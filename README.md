AWS Core for the XP Framework
========================================================================

[![Build status on GitHub](https://github.com/xp-forge/aws/workflows/Tests/badge.svg)](https://github.com/xp-forge/aws/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_0plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/aws/version.svg)](https://packagist.org/packages/xp-forge/aws)

Provides common AWS functionality in a low-level and therefore lightweight library (*less than 3% of the size of the official PHP SDK!*)

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

Credential providers
--------------------
AWS credentials are stored in various places, depending on the runtime environment. The *CredentialProvider* class supports the following:

* **Environment variables**: Uses `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` and (if present) `AWS_SESSION_TOKEN`
* **Shared credentials and config files**: Reads credentials from `~/.aws/config` and (if present) `~/.aws/credentials` (honoring alternative locations set via environment variables)
* **SSO**: Uses configured SSO and the cached credentials created by AWS CLI's [login](https://awscli.amazonaws.com/v2/documentation/api/latest/reference/sso/login.html) command, including SSO session support
* **Amazon ECS container credentials**: Uses the container API to fetch (and refresh, if necessary) the credentials

See https://docs.aws.amazon.com/sdkref/latest/guide/standardized-credentials.html

Sharing a S3 resource
---------------------

```php
use com\amazon\aws\{ServiceEndpoint, CredentialProvider};
use util\cmd\Console;

$s3= (new ServiceEndpoint('s3', CredentialProvider::default()))
  ->in('eu-central-1')
  ->using('my-bucket')
;
$link= $s3->sign('/path/to/resource.png', timeout: 180);

Console::writeLine($link);
```

Streaming uploads to S3
-----------------------

```php
use com\amazon\aws\api\SignatureV4;
use com\amazon\aws\{ServiceEndpoint, CredentialProvider};
use io\File;
use util\cmd\Console;

$s3= (new ServiceEndpoint('s3', CredentialProvider::default()))
  ->in('eu-central-1')
  ->using('my-bucket')
;

$file= new File('large.txt');
$file->open(File::READ);

try {
  $transfer= $s3->resource('target/{0}', [$file->filename])->open('PUT', [
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

Streaming responses from Bedrock AI models
------------------------------------------
See https://docs.aws.amazon.com/bedrock/latest/APIReference/API_runtime_ConverseStream.html:

```php
use com\amazon\aws\{ServiceEndpoint, CredentialProvider};
use util\cmd\Console;

$model= 'anthropic.claude-3-5-sonnet-20240620-v1:0';
$runtime= (new ServiceEndpoint('bedrock', CredentialProvider::default()))
  ->using('bedrock-runtime.')
  ->in('eu-central-1')
;

$response= $runtime->resource('/model/{0}/converse-stream', [$model])->transmit([
  'system' => [['text' => 'Use informal language']],
  'messages' => [
    ['role' => 'user', 'content' => [['text' => $argv[1]]]],
  ],
  'inferenceConfig' => [
    'maxTokens'   => 1000,
    'temperature' => 0.5,
  ],
]);
foreach ($response->events() as $event) {
  Console::writeLine($event->header(':event-type'), ': ', $event->value());
}
```


See also
--------
* [AWS Lambda for XP Framework](https://github.com/xp-forge/lambda)
* [AWS Lambda Webservices for the XP Framework](https://github.com/xp-forge/lambda-ws)
