# Supported Fields

Currently (29/08/17) the full set of elastic search fields are supported by this plugin. The following list shows what, if any, drupal field type they are mapped to.

### string
text and keyword
### Numeric datatypes
long, integer, short, byte, double, float
### Date datatype
date
### Boolean datatype
boolean
### Binary datatype
binary

## Complex datatypes
### Object datatype
object for single JSON objects
### Nested datatype
nested for arrays of JSON objects
## Geo datatypes
### Geo-point datatype
geo_point for lat/lon points
### Geo-Shape datatype
geo_shape for complex shapes like polygons

## Specialised datatypes
### IP datatype
ip for IPv4 and IPv6 addresses
### Completion datatype
completion to provide auto-complete suggestions
### Token count datatype
token_count to count the number of tokens in a string
### mapper-murmur3
murmur3 to compute hashes of values at index-time and store them in the index


# Unsupported Fields

### Attachment datatype
See the mapper-attachments plugin which supports indexing attachments like Microsoft Office formats, Open Document formats, ePub, HTML, etc. into an attachment datatype.
### Percolator type
Accepts queries from the query-dsl