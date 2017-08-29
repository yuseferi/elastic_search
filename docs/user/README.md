# Quickstart


To get the best out of elastic search you need to spend some amount of time setting it up. Although the elastic search module aims to deal with the complicated issues of index management in the background it expects you
to spend some time setting up the mappings for your data correctly.

## Server Configuration

## Entity Map Configuration

## Index Generation

## Document Indexing






It is important to note that it will not create the content type mappings for your automatically,
you must visit every content type you want and configure it.
Elastic search will try to guess the best type for your fields, but as nothing is perfect,
and you can enable multiple mappings for every field it is work spending some time on.
It is also worth nothing that mapping is done at an Entity, and not a field level,
therefore the way field A on content type X is not the same way as field A is mapped on content type Y.
