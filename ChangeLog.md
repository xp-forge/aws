AWS Core change log
===================

## ?.?.? / ????-??-??

* Merged PR #4: Implement signing a link, e.g. to share S3 resources. See
  https://docs.aws.amazon.com/AmazonS3/latest/userguide/ShareObjectPreSignedURL.html
  (@thekid)

## 1.1.0 / 2023-03-18

* Merged PR #3: Marshal and unmarshal payloads. Adds a dependency on the
  `xp-forge/marshalling` library
  (@thekid)

## 1.0.0 / 2023-03-15

* Fixed *Creation of dynamic property ... is deprecated* errors - @thekid
* Merged PR #2: Add `ServiceEndpoint::using()` to change the domain or the
  domain prefix for the endpoint
  (@thekid)

## 0.1.0 / 2023-03-15

* Merged PR #1: Add lightweight AWS service endpoint implementation - @thekid
* Hello World! First release - @thekid