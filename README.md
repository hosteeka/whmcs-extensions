## Development

### Getting started with this repository using Docker

1. Clone the repository
2. Create a whmcs directory in the root of the repository and copy the WHMCS files into it
3. Rename the install directory in the whmcs directory to any name
4. Rename the configuration.sample.php file in the whmcs directory to configuration.php and update the details (license key, database details). Don't forget to remove the return statement at the beginning of the file
5. Run `docker compose watch` to start the containers and watch for changes
6. Visit http://localhost/ and append the directory name you used in step 3 to the URL to access the WHMCS installation
7. Follow the WHMCS installation steps

Once you have completed the installation, you may proceed to develop the following in the root of the repository:

- includes
- modules
- templates

We follow the same directory structure as WHMCS. This allows for easily copying the required files into the WHMCS installation.
