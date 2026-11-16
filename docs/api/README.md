# WPRUS communication API

Connect an application to **WP Remote Users Sync (WPRUS) 2.4.0** to exchange user information with a connected site. Applications can send requests, receive requests, or do both. The communication format uses HTTPS, JSON, encrypted messages and security tokens.

## Connection settings

Obtain the following information from the administrator of the receiving site:

| Setting | Description |
| --- | --- |
| Receiving URL | Full HTTPS base address of the site, including any subdirectory |
| Sending URL | Your application's stable HTTPS base address, registered as a connected site |
| Encryption key | Secret shared by the connected sites for message encryption |
| Signature key | Secret shared by the connected sites for message verification |
| Allowed operations | Incoming operations enabled for the sending site |
| Allowed roles and metadata | The roles and metadata keys permitted for synchronization, if needed |
| Network access | The sending server's allowed IP address, if the receiver restricts POST requests |

Keep the two keys in private application settings. Both keys authorize access across the connected group, so share them only with trusted applications.

## Receive requests from WPRUS

An application can receive synchronization requests from WPRUS, send requests to WPRUS, or do both. To receive requests:

1. Provide the application’s stable HTTPS base URL to the WPRUS administrator so it can be registered as a connected site.
2. Obtain the shared encryption and signature keys through a secure channel.
3. Agree which actions WPRUS is allowed to send to the application.
4. Decide which account fields, roles and metadata keys the application will accept.
5. Expose the corresponding [API addresses](operations.md#endpoints) under the application's base URL.

Only applications trusted to communicate with the connected group should have access to its shared keys. See [Message format and security tokens](protocol.md#validate-incoming-requests) for receiving and checking requests.

## Make your first request

1. Encrypt a token request containing `{"action":"identity","method":"post","base_url":"https://receiver.example"}`.
2. Send `POST /wprus/token/` with JSON body `{"wprusdata":"<encrypted message>"}`.
3. Read the returned `nonce` and `expiry`. Use `nonce` as the token.
4. Encrypt the operation message with your registered **sending URL** in `base_url`.
5. Send `POST /wprus/identity/` with JSON body `{"wprusdata":"<encrypted message>","token":"<nonce>"}`.
6. Check the HTTP status and returned JSON. An identity response includes separate booleans for username and email existence.

For encryption, token expiration, and browser requests, see [Message format and tokens](protocol.md).

## Integration guides

- [Language-neutral client example](client-example.md)
- [Bash example](client-example-bash.md)
- [Python example](client-example-python.md)
- [Node.js example](client-example-nodejs.md)
- [Laravel example](client-example-laravel.md)
- [Operation reference and request examples](operations.md)
- [HTTP error codes and partial-success details](operations.md#error-responses-and-retries)
- [OpenAPI 3.1 specification](openapi.yaml)

The OpenAPI specification describes the HTTP request and response bodies. Each operation also has an `x-wprus-decrypted-schema` reference describing the JSON that must be encrypted into `wprusdata`.
