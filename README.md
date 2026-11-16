# OpenConect-devssp

This repo contains all files that are needed to get a SimpleSAMLphp based SAML IdP and SP running in a docker container 
with a configuration that can be used to test OpenConext-Stepup.

This image is not in any way production ready! It is meant for development and testing purposes only. 

This image is used in the docker-compose of the [OpenConext-devconf](https://github.com/OpenConext/OpenConext-devconf) project.

# EntraID mock IdP

devssp hosts a second IdP, at a separate base path on the same host, that emulates an EntraID (Azure AD) tenant.
It is meant for testing the [Stepup-AzureMFA](https://github.com/OpenConext/Stepup-AzureMFA) GSSP in OpenConext-devconf without a real EntraID tenant.

- Entity ID and metadata: `https://ssp.dev.openconext.local/entra-mock/saml2/idp/metadata.php`
- SingleSignOnService (HTTP-Redirect): `https://ssp.dev.openconext.local/entra-mock/module.php/saml/idp/singleSignOnService`

## Accounts

Log in with the same username and password as at the main IdP (e.g. `jane-a1` / `jane-a1`). The mock uses its own
authsource (`entra-mock-userpass` in `authsources.php`), so there is never SSO with the main IdP.

Use the special account `cancel` / `cancel` to simulate a user cancelling the authentication at EntraID: the mock
returns a SAML error response (Responder / AuthnFailed) to AzureMFA.

## Behaviour

- The AzureMFA SP (`https://azuremfa.dev.openconext.local/saml/metadata` is configured in `saml20-sp-remote.php`
  Its AuthnRequests must be signed, the signature is validated with `azuremfa_idp.crt`.
- ForceAuthn is set for the AzureMFA SP, so every AuthnRequest shows the login page.
- A Subject in the AuthnRequest is ignored (EntraID-style requests have none, ADFS-style requests do).
- The assertion is signed with `entra-mock-idp.key`; the response itself is not signed.
- The NameID has format `urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress` and contains the
  eduPersonPrincipalName (UPN) of the user, like EntraID.
- Only three attributes are released (see the `core:AttributeLimit` filter at the end of the mock's
  authproc in `saml20-idp-hosted.php`): `urn:mace:dir:attribute-def:mail`,
  `urn:mace:dir:attribute-def:eduPersonPrincipalName` and
  `http://schemas.microsoft.com/claims/authnmethodsreferences`.
- Stepup-AzureMFA uses the `mail` attribute to identify the user. It is released unchanged 
  (i.e. something@dev.openconext.local).
- The `http://schemas.microsoft.com/claims/authnmethodsreferences` attribute is added with value
  `http://schemas.microsoft.com/claims/multipleauthn`. Stepup-AzureMFA requires this for institutions with `is_azure_ad: true`.

The configuration is in `docker/sspconf`: the hosted IdP in `metadata/saml20-idp-hosted.php`, the AzureMFA SP in
`metadata/saml20-sp-remote.php`, the authsource in `authsources.php` and the `/entra-mock/` base path in `config.php` and `docker/conf/000-default.conf`.

## Testing the mock with the Test SP

The mock is available as Identity Provider "EntraID mock IdP" in the Test SP
([https://ssp.dev.openconext.local/simplesaml/sp.php](https://ssp.dev.openconext.local/simplesaml/sp.php)), so you
can see what the mock releases without going through AzureMFA. All hosted SPs (`default-sp` ... `fourth-sp`) are
accepted by the mock.

Add the following to the `institutions.yaml` of Stepup-AzureMFA to use it with the EntraID Mock IdP (recent
builds already have this configuration in their `institutions.yaml.dist`):

```yaml
dev.openconext.local:
  entity_id: 'https://ssp.dev.openconext.local/entra-mock/saml2/idp/metadata.php'
  sso_location: 'https://ssp.dev.openconext.local/entra-mock/module.php/saml/idp/singleSignOnService'
  certificates:
    - |
      <contents of entra-mock-idp.crt>
  email_domains:
    - 'dev.openconext.local'
  is_azure_ad: true
```

Instead of specifying `entity_id`, `sso_location` and `certificates` you can also use:

```yaml
dev.openconext.local:
  metadata_url: 'https://ssp.dev.openconext.local/entra-mock/saml2/idp/metadata.php'
  email_domains:
    - 'dev.openconext.local'
  is_azure_ad: true
```

# Development

## Setup
When developing the OpenConect-devssp itself you can run devssp using the docker-compose.yaml in this OpenConext-devssp project:

- Clone the [OpenConext-devconf](https://github.com/OpenConext/OpenConext-devconf) project on the same level as this project. 
- I.e. the directory structure should look like this:
    ```
    .
    ├── OpenConext-devconf
    └── OpenConext-devssp
    ```
- Ensure that ports 80 and 443 are available on the host
- Add the following to your `/etc/hosts` file:
    ```
    127.0.0.1 ssp.dev.openconext.local
    ```  

- Start the container from the root of OpenConext-devssp. You can use the `./dev-start.sh` script or run the following command:

  ```bash
  docker compose up
  ```

Then go to [https://ssp.dev.openconext.local/](https://ssp.dev.openconext.local/).
The proxy uses a self-signed certificate, so you will need to accept this certificate in your browser.

## dev-start.sh
  
You can use the `./dev-start.sh` script to start the containers in the background.
Ths script will copy the contents of the `/var/www` directory in the container to the `container/var/www` directory on 
the host each time the container is started so that source debugging of these files is possible
from the IDE.

With the `-r` option you can force a rebuild of the containers.
```bash
  ./dev-start.sh -r
```

## Debugging
The container has XDebug installed and configured.

### PhpStorm

The easiest way to develop and debug using PhpStorm is to use the remote interpreter feature:
1. Go to `Settings` > `PHP` > `CLI Interpreter`
2. Add a new Docker Compose interpreter
3. If you have not already done so, add the Docker Server you are using under Server
4. Configuration file is the `docker-compose.yaml` file in the root of this project
5. Service is `ssp`
6. Choose to connect to the existing container

To trace though e.g. the simplesaml php code, start the container with the dev-start.sh script. 
This copies the contents of the /var/www directory in the container to the container/var/www directory on the host.
Then you can map e.g. the container/var/www/simplesaml/src to the remote /var/www/simplesaml/src directory in PhpStorm.