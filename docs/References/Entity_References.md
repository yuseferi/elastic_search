# Entity References

Because elasticsearch does not allow multiple parent/child relationships, due to the child needing to be on the same shard as the parent (see [blog post](https://www.elastic.co/blog/managing-relations-inside-elasticsearch))
Elastic Search module attempts to combat this by following the reference and indexing it as a child document.
It will index the full document, according to the settings specified in the entities elastic settings.

Content type settings affect entity references in 2 ways

1) if the elastic mapping is not active entity references will not be recorded, and the entity reference field will simply be the id
2) if the elastic mapping is set to, 'child index only' then an individual document type will not be made in the index for this content type