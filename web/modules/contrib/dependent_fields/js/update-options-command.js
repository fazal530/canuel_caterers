(function ($, window, Drupal, drupalSettings) {
  Drupal.AjaxCommands.prototype.updateOptionsCommand = function (
    ajax,
    response,
    status,
  ) {
    const elementId = response.elementId;
    const options = response.options;
    const formatter = response.formatter;
    let element = null;
    let optionsArray = [];
    let currentSelection = [];
    let div = null;
    let input = null;
    let label = null;

    if (formatter === 'select') {
      element = $(`select[id^=${elementId}]`)[0];
      // Handle id's that changed by AJAX.
      if (element === null) {
        element = document.querySelector(
          `[data-drupal-selector="${response.elementId}"]`,
        );
      }
      // Perform update only for valid select element.
      if (element !== null) {
        element.options.length = 0;
        for (let i = 0; i <= options.length; i++) {
          if (options.hasOwnProperty(i)) {
            element.options.add(new Option(options[i].value, options[i].key));
          }
        }
      } else {
        element = document.getElementById(elementId);
        const fieldName = elementId.substr(5);
        if (element.className === 'form-radios') {
          optionsArray = Array.prototype.slice.call(
            element.querySelectorAll('.form-radio'),
          );
          currentSelection = optionsArray
            .filter((radioButton) => radioButton.checked)
            .map((radioButton) => radioButton.value);
        } else if (element.className === 'form-checkboxes') {
          optionsArray = Array.prototype.slice.call(
            element.querySelectorAll('.form-checkbox'),
          );
          currentSelection = optionsArray
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => checkbox.value);
        }
        // Remove the current options.
        while (element.firstChild) {
          element.removeChild(element.firstChild);
        }
        if (formatter === 'radios') {
          for (let j = 0; j <= options.length; j++) {
            if (j !== 0) {
              if (options.hasOwnProperty(j)) {
                div = document.createElement('div');
                div.setAttribute(
                  'class',
                  `js-form-item form-item js-form-type-radio form-type-radio js-form-item-${
                    fieldName
                  } form-item-${fieldName}`,
                );
                input = document.createElement('input');
                input.setAttribute(
                  'data-drupal-selector',
                  `${elementId}-${options[j].key}`,
                );
                input.setAttribute('type', 'radio');
                input.setAttribute('id', `${elementId}-${options[j].key}`);
                input.setAttribute('name', fieldName.replace(/[-]/g, '_'));
                input.setAttribute('value', options[j].key);
                input.setAttribute('class', 'form-radio');
                if (currentSelection.includes(options[j].key.toString())) {
                  input.setAttribute('checked', 'checked');
                }
                label = document.createElement('label');
                label.setAttribute('for', `${elementId}-${options[j].key}`);
                label.setAttribute('class', 'option');
                label.appendChild(document.createTextNode(options[j].value));
                div.appendChild(input);
                div.appendChild(document.createTextNode(' '));
                div.appendChild(label);
                element.appendChild(div);
              }
            }
          }
        } else if (formatter === 'checkboxes') {
          // Checkbox list.
          for (let k = 0; k <= options.length; k++) {
            if (k !== 0) {
              if (options.hasOwnProperty(k)) {
                const fieldNameOption = `${fieldName}-${options[k].key}`;
                div = document.createElement('div');
                div.setAttribute(
                  'class',
                  `js-form-item form-item js-form-type-checkbox form-type-checkbox js-form-item-${
                    fieldNameOption
                  } form-item-${fieldNameOption}`,
                );
                input = document.createElement('input');
                input.setAttribute(
                  'data-drupal-selector',
                  `${elementId}-${options[k].key}`,
                );
                input.setAttribute('type', 'checkbox');
                input.setAttribute('id', `${elementId}-${options[k].key}`);
                input.setAttribute(
                  'name',
                  `${fieldName.replace(/[-]/g, '_')}[${options[k].key}]`,
                );
                input.setAttribute('value', options[k].key);
                input.setAttribute('class', 'form-checkbox');
                if (currentSelection.includes(options[k].key.toString())) {
                  input.setAttribute('checked', 'checked');
                }
                label = document.createElement('label');
                label.setAttribute('for', `${elementId}-${options[k].key}`);
                label.setAttribute('class', 'option');
                label.appendChild(document.createTextNode(options[k].value));
                div.appendChild(input);
                div.appendChild(document.createTextNode(' '));
                div.appendChild(label);
                element.appendChild(div);
              }
            }
          }
        }
      }
      const event = new Event('change');
      element.dispatchEvent(event);
    }
  };
})(jQuery, window, Drupal, drupalSettings);
