AWS Core change log
===================

## ?.?.? / ????-??-??

## 2.4.0 / 2024-08-06

* Added support for SSO sessions, making the default credential provider
  compatible when running `aws configure sso` with newer AWS CLI versions.
  Fixes issue #14
  (@thekid)

## 2.3.0 / 2024-08-02

* Merged PR #13: Add optional parameter "type" to `Resource::transmit()`
  (@thekid)

## 2.2.0 / 2024-07-13

* Included `LAMBDA_TASK_ROOT` in home directory lookup - @thekid

## 2.1.0 / 2024-07-07

* Merged PR #12: Support for SSO credential provider - @thekid

## 2.0.1 / 2024-07-07

* Fixed separating request parameters from request URI - @thekid

## 2.0.0 / 2024-07-05

* **Heads up**: Refactored `com.amazon.aws.credentials.Provider` from an
  interface to an abstract base class!
  (@thekid)
* Implemented PR #11: Accept credential functions in `ServiceEndpoint`
  constructor
  (@thekid)

## 1.8.2 / 2024-07-07

* Fixed separating request parameters from request URI - @thekid

## 1.8.1 / 2024-06-30

* Fixed issue #10: There were headers present in the request which were
  not signed
  (@thekid)
* Added the constant `SignatureV4::NO_PAYLOAD` which is equal to the pre-
  calculated sha256 hash of an empty string
  (@thekid)

## 1.8.0 / 2024-06-30

* Added `CredentialProvider::none()` which never provides any credentials
  (@thekid)
* Merged PR #9: Implement credential providers. Initial support for these
  providers: *Environment variables*, *Shared credentials and config files*
  and *Amazon ECS container credentials*.
  (@thekid)

## 1.7.0 / 2024-06-29

* Merged PR #8: Add possibility to stream requests to AWS endpoints. Useful
  for transferring large payloads without blocking, e.g. S3 uploads.
  (@thekid)

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