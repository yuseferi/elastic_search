# Adding Entity Support

To make your entity available to elastic search you simply implement a plugin of type ElasticEnabledEntity

The id of this plugin MUST match the id of the entity form that you wish to enable. for example 'node_type' or 'taxonomy_vocabulary'

Entities are able to alter any parameters passed to the mapping field form.
This is so that, for example, when attaching a form to an entity of node_type you can still pass the correct entity type 'node' to the form