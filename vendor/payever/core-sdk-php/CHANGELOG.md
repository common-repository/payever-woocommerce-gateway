# Changelog

## [2.1.0]
### Added
- Created helper for entities creation `DataHelper:getEntityInstance($data, $className)`

### Changed
- Minimal version of PHP - 5.6
- `StringHelper::jsonDecode()` improved json decoding error handling
- `StringHelper::jsonDecode()` changed behavior: it's the same behavior as `json_decode()`
- `StringHelper::jsonDecode()` added additional parameters: `$object, $associative = null, $depth = 512, $flags = 0`

## [2.0.0]
### Added
- `ApmProcessor` for apm logging using Monolog

### Removed
- Removed `ApmLogger`, `FileLogger`, `NullLogger`
- Removed `psr/log` dependency

### Changed
- `ClientConfiguration::setLogger`: apply ApmProcessor if have the Monolog logger

## [1.2.2]
### Bugfixes
- Use CURL_HTTP_VERSION_1_1 constant as default http version;

## [1.2.1]
### Bugfixes
- Psr\Log: php 5.6 compatibility issue;

## [1.2.0]
### Added
- Added `x-payever-force-retry` header
- Added `idempotency-key` header

## [1.1.7]
### Bugfixes
- Psr\Log: compatibility issue v1.*

## [1.1.6]
### Added
- Added `CHANNEL_OROCOMMERCE` const

## [1.1.5]
### Added
- Added `ChannelTypeSet` class

### Changed
- OroCommerce value in `ChannelSet` class
- Check result of `json_encode`

## [1.1.4]
### Bugfixes
- Apm logger trait implementation;
 
## [1.1.3]
### Bugfixes
- Psr\Log: compatibility issue;

## [1.1.2]
### Bugfixes
- php-cli: function getallheaders not exist;

## [1.1.1]
### Added
- APM implementation;

## [1.0.0]
### Added
- initial version;
