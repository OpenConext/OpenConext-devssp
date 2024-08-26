# OpenConect-devssp

This repo contains all files that are needed to get a SimpleSAMLphp based SAML IdP and SP running in a docker container with a configuration that can be used to test OpenConext-Stepup.

This container is not in any way production ready! It is meant for development purposes only. 

The container is used in the docker-compose of the OpenConect-devconf project

# Docker-compose
To run the devssp using the docker-compose.yaml from the OpenConext-devconf project:

- Clone the [OpenConext-devconf](https://github.com/OpenConext/OpenConext-devconf) project on the same level as this project. I.e. the directory structure should look like this:
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
  
- ./dev-rebuild.sh will rebuild the container and pull the latest base image

Both the ./dev-start.sh and ./dev-rebuild.sh scripts will copy the simplesaml directory from the contain to ./container-files on your hosts. This way you can use xdebug with the actual source files from the container by setting op a local path mapping in your IDE. 

Then go to [https://ssp.dev.openconext.local/](https://ssp.dev.openconext.local/). The proxy uses a self-signed certificate, so you will need to accept this certificate in your browser.


