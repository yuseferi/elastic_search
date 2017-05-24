# FieldDefinitionsPlugin

Elastic Search provides a way to alter the way that field definitions are retrieved for the mapping procedure.

You alter the field definitions you will need to implement a FieldDefinitions plugin, and give it an id that corresponds with the Entity type.

## A practical example of why you might need this

 Taxonomy vocabularies have a mapping form attached to them, but the mapping actually comes from the term entity and not the vocab entity.
 Therefore we need to alter the mapping so that we get the correct turn field definitions to show on the page

## Generic field definitions

Most Entities that use bundles do not need to provide a FieldDefinitions plugin implementation as they can use the included Generic solution