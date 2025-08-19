<?php

namespace Drupal\entityreference_filter\Ajax;

use Drupal\Core\Ajax\HtmlCommand;

/**
 * AJAX command for calling the jQuery html() method.
 *
 * The 'EntityReferenceFilterInsertNoWrapCommand/html' command instructs
 * the client to use jQuery's html() method
 * to set the HTML content of each element matched by the given selector while
 * leaving the outer tags intact.
 *
 * This command is implemented by
 * Drupal.AjaxCommands.prototype.EntityReferenceFilterInsertNoWrapCommand()
 * defined in js/entityreference_filter.js.
 *
 * @see https://api.jquery.com/html/
 *
 * @ingroup ajax
 */
class EntityReferenceFilterInsertNoWrapCommand extends HtmlCommand {

  /**
   * Implements Drupal\Core\Ajax\CommandInterface:render().
   */
  public function render() {

    return [
      'command' => 'entityReferenceFilterInsertNoWrapCommand',
      'method' => 'html',
      'selector' => $this->selector,
      'data' => $this->getRenderedContent(),
      'settings' => $this->settings,
    ];
  }

}
