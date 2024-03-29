AWS Core change log
===================

## ?.?.? / ????-??-??

## 1.6.0 / 2024-03-24

* Made compatible with XP 12 - @thekid

## 1.5.0 / 2023-12-02

* Added PHP 8.4 to the test matrix - @thekid
* Merged PR #6: Allow overwriting user agent via headers - @thekid

## 1.4.0 / 2023-09-24

* Added compatibility with `xp-forge/marshalling` v2.0.0 - @thekid

## 1.3.0 / 2023-06-06

* Merged PR #5: Move responsibility for processing headers to endpoint
  implementation
  (@thekid)

## 1.2.0 / 2023-06-06

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