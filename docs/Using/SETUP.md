# Setting up your site to use elasticsearch

To get the best out of elastic search you need to spend some amount of time setting it up.

Although the elastic search module aims to deal with the complicated issues of index management in the background it expects you
to spend some time setting up the mappings for your data correctly.

It is important to note that it will not create the content type mappings for your automatically,
you must visit every content type you want and configure it.
Elastic search will try to guess the best type for your fields, but as nothing is perfect,
and you can enable multiple mappings for every field it is work spending some time on.
It is also worth nothing that mapping is done at an Entity, and not a field level,
therefore the way field A on content type X is not the same way as field A is mapped on content type Y.

TODO..... stuff about how to configure

## Entity References

Entity references do not play nicely with elasticsearch, and do not allow us to get the best out of its search capabilities
Therefore the elastic search plugin comes with an option to map documents internally

Because elastic has no concept of many to one relationships, the elasticsearch plugin stores all the references of the document as sub fields.
This has some important implications.

* All referenced content types must have an elastic mapping
* Updating an entity will cause a search for that entity in your index, and a replacement of ALL occurences of it in the index
* Additional storage will be consumed

When sending the mapping to the server your mappings will be validated. This important because of document references.
If you have not setup the mapping for an entity type that is a reference the mapping will refuse to send.