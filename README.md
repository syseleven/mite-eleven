SysEleven/MiteEleven
====================

Introduction
------------

SysEleven/MiteEleven implements a simple wrapper to Mite time tracking &copy RESTful API written in PHP. It encapsulates methods for retrieving, creating and manipulating time entries, customers, projects, services and users.

Requirements
------------

The requirements are pretty basic:

    > php >= 5.3.19 or php >= 5.4.8 there is a bug in prior version of which prevented FILTER_VALIDATE_BOOLEAN to validate "false" correctly
    > Zend\Http (will be automatically installed if using composer)

and for development or if you want to run the tests:

    > mockery/mockery (will be automatically installed using composer)
    > phpunit >= 3.6

Installation
------------

The recommended way to install is using composer, you can obtain the latest version of composer at http://getcomposer.org.

Simply add MiteEleven to your requirements and specify the repository as follows:

    ```json
     {
        "require": {
            "syseleven/mite-eleven": "dev-master",
        },
        "repositories": [
                {
                    "type": "vcs",
                    "url": "https://github.com/syseleven/mite-eleven.git"
                }
        ]
     }
    ```

Then run composer to update your dependencies:

    ```bash
     $ php composer.phar update
     ```
or if you want run the tests:

     ```
     $ php composer.phar update --dev
     ```

If you don't want to use composer simply clone the repository to a location of your choice

    ```bash
    $ git clone https://github.com/syseleven/mite-eleven.git
    ```


Usage
-----

    ```php
    use SysEleven\MiteEleven\MiteClient;
    use SysEleven\MiteEleven\RestClient;

    // Setting up the connection
    // Create a rest object with your Mite Url and your API key
    $rest = new RestClient('https://subdomain.mite.yo.lk','Your_Api_Key');

    // By default the rest client uses \Zend\Http\Client\Adapter\Stream
    // if you want to change it to something else eg. cURL, create an instance
    // of the adapter and pass it to RestClient::setAdapter()

    $rest->setAdapter(new \Zend\Http\Client\Adapter\Curl());

    // You have to set CURLOPT_SSL_VERIFYPEER to 0
    $rest->getAdapter()->setCurlOption(CURLOPT_SSL_VERIFYPEER, 0);

    // Then finally create a MiteClient instance and pass the RestClient to it
    $mite = new MiteClient($rest);

    // Then find more out about yourself
    var_dump($mite->getMyself());

    ```



