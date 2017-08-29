# Entity References

Because elastic has no concept of many to one relationships (see [this blog post](https://www.elastic.co/blog/managing-relations-inside-elasticsearch)) Entity References do not play nicely with elasticsearch out of the box, and do not allow us to get the best out of its search capabilities
Therefore the elastic search plugin comes with an option to map referenced documents inside their parent document directly.

This has some important implications.

* All entity referenced types must have a separate elastic mapping
* Updating an entity will cause a search for that entity in your index, and a replacement of ALL occurrences of it in the index
* Additional storage will be consumed

A number of settings affect the way that this is handled in your index

* Recursion Depth
    Below the maximum recursion depth the document will be inlined into the parent, above this depth it will be a simple id value. This allows deep nestings and can be used to stop circular references
* Child Only
    If the document is set to child only on the server it will not get it's own index(or indices) and the field settings will only be used when inlining the entity type into the parent (if within the max recursion depth)
