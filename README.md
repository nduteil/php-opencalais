# php-opencalais

A PHP package for [Thomson Reuters Open Calais API](http://www.opencalais.com/)

Open Calais is a web service that returns semantic information from submitted texts, using Natural Language Processing (NLP), Name Entity Recognition (NER) and machine learning : "Open Calais processes the text you submit and returns: Entities, Topic codes, Events, Relations and SocialTags."

The use of this package requires an API token.

## Requirements

- PHP 5.6+
- PHP Curl extension
- PHP XML extension (optional, but highly recommended)

## Installation

Install php-opencalais through [Composer](http://getcomposer.org)

```bash
$ composer require nduteil/opencalais
```

## Usage

```php
$oc = new OpenCalais('YOUR_API_KEY');

// get raw API response...
$response = $oc->queryAPI($documentString);

// ... or get an entities array
$entities = $oc->getEntities($documentString);

// Some default can be overrided through constructor
$oc = new OpenCalais('YOUR_API_KEY', 'text/xml', 'xml/rdf')

```
Have a look on php-opencalais-demo.php for a short use case


