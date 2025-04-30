# OpenConect-devssp

This repo contains all files that are needed to get a SimpleSAMLphp based SAML IdP and SP running in a docker container 
with a configuration that can be used to test OpenConext-Stepup.

This image is not in any way production ready! It is meant for development and testing purposes only. 

This image is used in the docker-compose of the [OpenConext-devconf](https://github.com/OpenConext/OpenConext-devconf) project.

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