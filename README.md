# jsonapi_embed
[![CircleCI](https://circleci.com/gh/integratedexperts/jsonapi_embed.svg?style=svg&circle-token=937353462df6ab8552f7f52d4be162efe5e90d7c)](https://circleci.com/gh/integratedexperts/jsonapi_embed)

Drupal module that allows to embed entities, which are referenced via Entity Reference fields, into parent entities in JSONAPI REST responses.

## Configuration

1. Navigate to any entity reference (or derivative) field settings page.
2. Check `Render entity in JSON API as embedded` checkbox to alter JSON API behavior.

To verify changes, navigate to the JSON API feed (e.g. `/jsonapi/node/article`), find that the field (`field_tags`, for the example) is now exposed  in `attributes` rather then `relationship` attributes.
