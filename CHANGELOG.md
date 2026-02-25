# TOR-PHP Changelog

This file contains information about every addition, update and deletion in the `ecourty/tor-php` library.  
It is recommended to read this file before updating the library to a new version.

## v1.2.0

#### Additions

- Added [`KeyPairGenerator`](./src/Helper/KeyPairGenerator.php) for generating ED25519-V3 keypairs for Tor onion services
  - Uses native PHP `sodium` extension for cryptographically secure key generation
  - Derives `.onion` service IDs according to Tor v3 specification (base32 encoding with SHA3-256 checksum)
  - Keys are properly expanded to match Tor's expected format
- Added [`KeyPair`](./src/Model/KeyPair.php) readonly model class
  - Stores private key, public key, and derived service ID
  - Provides multiple key format methods: raw, base64, and Tor-formatted (uses `PrivateKeyHelper`)
  - Includes `__debugInfo()` to prevent sensitive data leaks in dumps
- Added comprehensive unit tests for key generation and model
- Added integration tests validating generated keys work with actual Tor instances
- Added `selective/base32` dependency for RFC 4648 compliant base32 encoding
- Added `ext-sodium` to `suggest` in composer.json (required for `KeyPairGenerator`, but not for other features)

#### Updates

- Updated `.php-cs-fixer.php`: disabled `mb_str_functions` rule (required for binary-safe string operations with cryptographic keys)
- Updated README.md with key generation examples
- `KeyPair::getPrivateKeyFormatted()` now uses `PrivateKeyHelper::parsePrivateKey()` for consistency

## v1.1.0

#### Additions

- Added the [`TorHttpClient::getExitNodes`](./src/TorHttpClient.php) method which returns all current peers on the Tor network in a Generator.
  - Added [`ExitNodeHelper`](./src/Helper/ExitNodesHelper.php) helper to extract data from the Tor response.
- Added tests for this method.
- Added a [code example](./examples/get_exit_nodes.php) for getting exit nodes.

#### Updates

- Bumped the minimum PHP version to `>= 8.4` in [composer.json](./composer.json)

## v1.0.0

Initial release of the project.

#### Additions

- Added the [`TorPHP\TorHttpClient`](./src/TorHttpClient.php) to handle Tor-proxied HTTP requests.
  - Implements `Symfony\Contracts\HttpClient\HttpClientInterface` for easy integration with other projects.
  - Integrates [`TorPHP\TorControlClient`](./src/TorControlClient.php) to handle Tor circuit change.

- Added the [`TorPHP\TorControlClient`](./src/TorControlClient.php) to handle TorControl commands.
  - Integrates [`TorPHP\Transport\TorSocketClient`](./src/Transport/TorSocketClient.php) to handle TorControl socket connections.
  - Supports the following:
    - Gather current node's circuits
    - Get / Set configuration options
    - List current Onion services hosted on the node
    - Creating / Deleting Onion services
  - Handles authentication with password or cookie (or none).

- Added the [`TorPHP\Transport\TorSocketClient`](./src/Transport/TorSocketClient.php) to handle TorControl socket connections.
- Added unit tests under [tests](./tests)
- Added code examples under [examples](./examples)
