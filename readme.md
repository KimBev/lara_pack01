## coinloft_payment

coinloft_payment package contains 'adapters' that connect to external payment providers and gateways.

The adapter should abstract the complexity of the provider's api and provide a simple set of function based methods that can be used by the consuming application.

## Installation

Following steps are required to install via composer.json

    "repositories":[
        {
            "type":"vcs",
            "url":"git@git.btccorp-git.com.au:givbtc/coinloft_payment.git",
            "no-api": true
        }
    ],
    "require": {
        "coinloft/payment": "dev-master"
    },
