# Configuring your Entity types


# Fields can have multiple mappings

Because of the way elastic deals with nested documents, and drupal 8's concept that all fields could have multiple values fields must be configured to deal with this
Elastic Search module will automatically detect if the cardinality of the field is one or greater.
- If it is one
    The type specified will be used
- If the cardinality is > 1
    The type Nested will be used, and the type set in the config field will be used for the property of the nested type field mapping

