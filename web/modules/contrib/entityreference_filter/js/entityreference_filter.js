((Drupal, $) => {
  Drupal.behaviors.entityReferenceFilter = {
    attach(context, settings) {
      if (settings.entityreference_filter) {
        $.each(settings.entityreference_filter, (formId, filterSetting) => {
          const form = $(`#${formId}`, context);
          if (form.length === 0) {
            return;
          }

          const { dependent_filters_data: dependentFiltersData } =
            filterSetting;
          let { ajax_path: ajaxPath } = filterSetting.view;

          if (Array.isArray(ajaxPath)) {
            [ajaxPath] = ajaxPath;
          }

          const controllingFilters = {};
          const controllingFiltersNames = {};
          const dependentFilters = {};
          const dependentFiltersNames = {};

          // Build controlling and dependent filters array to react
          // on their change and collect their elements and names.
          if (dependentFilters) {
            $.each(
              dependentFiltersData,
              (dependentFilterName, depControllingFilters) => {
                $.each(
                  depControllingFilters,
                  (index, controllingFilterName) => {
                    // Dependent filters
                    const elementDependentFilter = form.find(
                      `[name="${dependentFilterName}"],[name="${dependentFilterName}[]"]`,
                    );
                    if (elementDependentFilter.length > 0) {
                      // disable autocomplete.
                      elementDependentFilter.attr('autocomplete', 'off');

                      dependentFilters[dependentFilterName] =
                        elementDependentFilter;
                      dependentFiltersNames[dependentFilterName] =
                        dependentFilterName;
                    }

                    // Controlling filters
                    const elementControllingFilter = form.find(
                      `[name="${controllingFilterName}"],[name="${controllingFilterName}[]"]`,
                    );
                    if (elementControllingFilter.length > 0) {
                      // disable autocomplete.
                      elementControllingFilter.attr('autocomplete', 'off');

                      controllingFilters[controllingFilterName] =
                        elementControllingFilter;
                      controllingFiltersNames[controllingFilterName] =
                        controllingFilterName;
                    }
                  },
                );
              },
            );
          }

          $.each(controllingFilters, (filterName, filterElement) => {
            $(once('entityreference_filter', filterElement)).change((event) => {
              const submitValues = {};

              // get current input data RAW.
              // Controlling filters.
              $.each(controllingFilters, (filterN, filterEl) => {
                submitValues[filterN] = filterEl.val();
              });

              // Dependent filters.
              $.each(dependentFilters, (filterN, filterEl) => {
                submitValues[filterN] = filterEl.val();
              });

              $.extend(submitValues, filterSetting, {
                controlling_filters: controllingFiltersNames,
                dependent_filters: dependentFiltersNames,
                form_id: formId,
              });

              const elementSettings = {
                url: ajaxPath,
                submit: submitValues,
              };

              const ajax = new Drupal.Ajax(false, false, elementSettings);

              // Send request
              ajax.eventResponse(ajax, event);
            });
          });
        });
      }
    },
  };

  /**
   * Command to insert new content into the DOM without wrapping in extra DIV
   * element.
   */
  Drupal.AjaxCommands.prototype.entityReferenceFilterInsertNoWrapCommand = (
    ajax,
    response,
  ) => {
    // Get information from the response. If it is not there, default to
    // our presets.
    const wrapper = response.selector ? $(response.selector) : $(ajax.wrapper);
    const method = response.method || ajax.method;
    const effect = ajax.getEffect(response);

    // We don't know what response.data contains: it might be a string of text
    // without HTML, so don't rely on jQuery correctly interpreting
    // $(response.data) as new HTML rather than a CSS selector. Also, if
    // response.data contains top-level text nodes, they get lost with either
    // $(response.data) or $('<div></div>').replaceWith(response.data).
    const newContentWrapped = $('<div></div>').html(response.data);
    const newContent = newContentWrapped.contents();

    // If removing content from the wrapper, detach behaviors first.
    let settings = response.settings || ajax.settings || Drupal.settings || {};
    const wrapperEl = wrapper.get(0);

    Drupal.detachBehaviors(wrapperEl, settings);

    // Show or hide filter depending on its values and
    // `hide empty filter` option
    const elHidden = wrapper.parent().parent().hasClass('hidden');
    const elHasValues = settings.has_values;
    const hideEmptyFilter = settings.hide_empty_filter;

    if (hideEmptyFilter) {
      if (!elHasValues && !elHidden) {
        wrapper.parent().wrap('<div class="hidden"></div>');
      }
      if (elHasValues && elHidden) {
        wrapper.parent().unwrap();
      }
    }

    // Add the new content to the page.
    wrapper[method](newContent);

    // Immediately hide the new content if we're using any effects.
    if (effect.showEffect !== 'show') {
      newContent.hide();
    }

    // @todo what is it ?
    // Determine which effect to use and what content will receive the
    // effect, then show the new content.
    if ($('.ajax-new-content', newContent).length > 0) {
      $('.ajax-new-content', newContent).hide();
      newContent.show();
      $('.ajax-new-content', newContent)[effect.showEffect](effect.showSpeed);
    } else if (effect.showEffect !== 'show') {
      newContent[effect.showEffect](effect.showSpeed);
    }

    // Attach all JavaScript behaviors to the new content, if it was
    // successfully added to the page, this if statement allows
    // #ajax['wrapper'] to be optional.
    if (newContent.parents('html').length > 0) {
      // Apply any settings from the returned JSON if available.
      settings = response.settings || ajax.settings || Drupal.settings;
      Drupal.attachBehaviors(wrapperEl, settings);
    }
  };
})(Drupal, jQuery);
