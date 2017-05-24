# Adding support for your field

If you wish to add a custom field type to an elastic search mapping type then you can listen for the event
`'elastic_search.field_mapper.supports.' . $this->getElasticType()`

once this is done you can alter the array of what field types are supported and set it back in the event object
