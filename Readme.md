# Elastic Search

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ibrows/drupal_elastic_search/badges/quality-score.png?b=master&s=f10ea17f4022aa1b5aafb9d39c615428f2ec3645)](https://scrutinizer-ci.com/g/ibrows/drupal_elastic_search/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/ibrows/drupal_elastic_search/badges/build.png?b=master&s=f7ac758793ad9c29394db74b65687f7f850ff223)](https://scrutinizer-ci.com/g/ibrows/drupal_elastic_search/build-status/master)


## In a nutshell

The elastic search module attempts to put elastic search paradigms at the heart of drupal searching.

This means:
    - Uses the elasticsearch/elasticsearch libary
    - Module controls the indexes for you, to fully exploit  multilingual analysers and optimize document structure
    - Entity references are converted to inline documents for full searching capability
    - Content types can be excluded from the index, or made to be mapped as inner documents / children only
    - Easy to extend type mappings to your custom fields by implementing an event subscriber

# Requirements

* PHP >=7.0
* Ace code editor. Add this to your libraries/ folder so /libraries/ace/src-min-noconflict/ace.js is available

# Notes

Index documents in bulk and update indexes and mappings in small groups. This seems to produce the best results for cluster memory and to reduce potential timeouts
By default index and mapping operations are done 1 at a time and documents are indexed in batches of 100. These settings can be changed on the server config page
Because batches involve writing to the database you need to be careful that the total number does not cause an insert query which is too large. 100-200 is optimal

Debug logging in elasticsearch uses print_r and the drupal log reports pages dont want to show this. If you need to debug with this you are best looking directly at the watchdog table or using drupal console

# Mapping

Type field should be mapped as a simple reference and not an object due to their representation in entities

# Supports

* Multifield mapping
    https://www.elastic.co/guide/en/elasticsearch/guide/2.x/most-fields.html#_multifield_mapping

## Testing

elastic_search uses [Mockery](http://docs.mockery.io) for test mocking because of a bug in the phpunit version that drupal requires which prevents some classes from being mocked