# Selector text operators (3.0.160)
description: Use new text operators for search-like queries.
## Blueprints
## Steps
- Match partial words with ~*=
- Live search last word partial with ~~= 
- LIKE-based within-word match with ~%=
- Query expansion with ~+=
- Any-word OR matching with ~|=
## Request
title matches advanced patterns
## Response
Pages matching text criteria
## Example
```php
<?php
wire()->pages->find("title~*=web image");
wire()->pages->find("title~~=api pro");
wire()->pages->find("title~%=build site");
wire()->pages->find("title~+=books");
wire()->pages->find("title~|=architecture engineering construction");
```
## Compatibility
Refer to linked blueprints for @since version notes on classes and methods.
