<?php

namespace Drupal\entityreference_filter\Plugin\views\filter;

use Drupal\search_api\Plugin\views\filter\SearchApiFilterTrait;

/**
 * Filter by entity id using items got from entity reference view.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("entityreference_filter_view_result_search_api")
 *
 * @see \Drupal\entityreference_filter\Plugin\views\filter\EntityReferenceFilterViewResult
 */
class EntityReferenceFilterViewResultSearchApi extends EntityReferenceFilterViewResult {
  use SearchApiFilterTrait;

}
