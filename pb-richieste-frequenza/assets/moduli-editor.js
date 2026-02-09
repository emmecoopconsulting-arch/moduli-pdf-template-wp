(function ($) {
  const FIELD_TYPES = [
    { value: 'text', label: 'Testo' },
    { value: 'email', label: 'Email' },
    { value: 'tel', label: 'Telefono' },
    { value: 'date', label: 'Data' },
    { value: 'textarea', label: 'Testo lungo' },
    { value: 'select', label: 'Selezione' },
    { value: 'select_sede', label: 'Seleziona sede' },
  ];

  const DEFAULT_FIELD = () => ({
    name: '',
    label: '',
    type: 'text',
    required: false,
    group: '',
    options: [],
  });

  function parseSchema(raw) {
    if (!raw) return [];
    try {
      const parsed = JSON.parse(raw);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  }

  function sanitizeName(value) {
    return String(value || '')
      .trim()
      .replace(/[^\w\-]+/g, '_')
      .replace(/_{2,}/g, '_')
      .replace(/^_+|_+$/g, '');
  }

  function createFieldRow(field, index) {
    const $field = $('<div/>', { class: 'pb-schema-field', 'data-index': index });
    const titleText = field.label || field.name || `Campo ${index + 1}`;
    const $header = $('<div/>', { class: 'pb-schema-field-header' });
    const $title = $('<div/>', { class: 'pb-schema-field-title', text: titleText });
    const $actions = $('<div/>', { class: 'pb-schema-field-actions' });

    $actions.append(
      $('<button/>', { type: 'button', class: 'button-link pb-move-up', text: 'Su' }),
      $('<button/>', { type: 'button', class: 'button-link pb-move-down', text: 'Giù' }),
      $('<button/>', { type: 'button', class: 'button-link-delete pb-remove-field', text: 'Rimuovi' })
    );

    $header.append($title, $actions);

    const $grid = $('<div/>', { class: 'pb-schema-field-grid' });
    const $placeholder = $('<div/>', { class: 'pb-schema-helper pb-schema-placeholder' });

    $grid.append(
      $('<label/>').append('Nome campo', $('<input/>', { type: 'text', class: 'pb-field-name', value: field.name || '' })),
      $('<label/>').append('Etichetta', $('<input/>', { type: 'text', class: 'pb-field-label', value: field.label || '' })),
      $('<label/>').append(
        'Tipo',
        $('<select/>', { class: 'pb-field-type' }).append(
          FIELD_TYPES.map((type) => $('<option/>', { value: type.value, text: type.label, selected: type.value === field.type }))
        )
      ),
      $('<label/>').append('Gruppo', $('<input/>', { type: 'text', class: 'pb-field-group', value: field.group || '' }))
    );

    const $required = $('<label/>', { class: 'pb-schema-required' });
    $required.append(
      $('<input/>', { type: 'checkbox', class: 'pb-field-required', checked: !!field.required }),
      $('<span/>', { text: 'Obbligatorio' })
    );
    $grid.append($required);

    const $options = $('<div/>', { class: 'pb-schema-options' });
    const $optionsList = $('<div/>', { class: 'pb-schema-options-list' });
    const options = Array.isArray(field.options) ? field.options : [];

    options.forEach((option, optionIndex) => {
      $optionsList.append(createOptionRow(option, optionIndex));
    });

    $options.append(
      $('<div/>', { text: 'Opzioni (solo per select):', class: 'pb-schema-helper' }),
      $optionsList,
      $('<button/>', { type: 'button', class: 'button pb-add-option', text: 'Aggiungi opzione' })
    );

    $field.append($header, $grid, $placeholder, $options);
    toggleOptions($field, field.type);
    updatePlaceholderHint($field);
    return $field;
  }

  function createOptionRow(option, index) {
    const $row = $('<div/>', { class: 'pb-schema-option', 'data-option-index': index });
    $row.append(
      $('<input/>', { type: 'text', class: 'pb-option-value', placeholder: 'Valore', value: option.value || '' }),
      $('<input/>', { type: 'text', class: 'pb-option-label', placeholder: 'Etichetta', value: option.label || '' }),
      $('<button/>', { type: 'button', class: 'button-link-delete pb-remove-option', text: 'Rimuovi' })
    );
    return $row;
  }

  function toggleOptions($field, type) {
    const $options = $field.find('.pb-schema-options');
    if (type === 'select') {
      $options.show();
    } else {
      $options.hide();
    }
  }

  function collectSchema($fields) {
    const schema = [];
    $fields.each(function () {
      const $field = $(this);
      const type = $field.find('.pb-field-type').val();
      const name = sanitizeName($field.find('.pb-field-name').val());
      const label = $field.find('.pb-field-label').val();
      const group = $field.find('.pb-field-group').val();
      const required = $field.find('.pb-field-required').is(':checked');
      const fieldData = {
        name,
        label,
        type,
        required,
        group,
      };

      if (type === 'select') {
        const options = [];
        $field.find('.pb-schema-option').each(function () {
          const value = $(this).find('.pb-option-value').val();
          const labelValue = $(this).find('.pb-option-label').val();
          if (value || labelValue) {
            options.push({ value: value || '', label: labelValue || '' });
          }
        });
        fieldData.options = options;
      }

      schema.push(fieldData);
    });
    return schema;
  }

  function updatePlaceholderHint($field) {
    const nameValue = sanitizeName($field.find('.pb-field-name').val());
    const placeholder = nameValue ? `Placeholder: \${${nameValue}}` : 'Imposta il nome campo per ottenere il placeholder.';
    $field.find('.pb-schema-placeholder').text(placeholder);
  }

  $(function () {
    const $editor = $('#pb-schema-editor');
    const $textarea = $('#pb-schema-json');
    if (!$editor.length || !$textarea.length) {
      return;
    }

    let schema = parseSchema($textarea.val());
    if (!schema.length) {
      schema = [];
    }

    const $toolbar = $('<div/>', { class: 'pb-schema-toolbar' });
    const $addField = $('<button/>', { type: 'button', class: 'button button-primary', text: 'Aggiungi campo' });

    $toolbar.append($addField);
    const $fieldsWrapper = $('<div/>', { class: 'pb-schema-fields' });
    const $helper = $('<div/>', {
      class: 'pb-schema-helper',
      text: 'Suggerimento: usa nomi campo senza spazi (es. genitore_nome).',
    });

    $editor.append($toolbar, $fieldsWrapper, $helper);

    function render() {
      $fieldsWrapper.empty();
      schema.forEach((field, index) => {
        $fieldsWrapper.append(createFieldRow(field, index));
      });
      syncTextarea();
    }

    function syncTextarea() {
      const newSchema = collectSchema($fieldsWrapper.find('.pb-schema-field'));
      $textarea.val(JSON.stringify(newSchema, null, 2));
      $fieldsWrapper
        .find('.pb-schema-field')
        .each(function () {
          const $field = $(this);
          const title = $field.find('.pb-field-label').val() || $field.find('.pb-field-name').val() || 'Campo';
          $field.find('.pb-schema-field-title').text(title);
        });
    }

    $addField.on('click', () => {
      schema.push(DEFAULT_FIELD());
      render();
    });

    $fieldsWrapper.on('click', '.pb-remove-field', function () {
      const index = $(this).closest('.pb-schema-field').data('index');
      schema.splice(index, 1);
      render();
    });

    $fieldsWrapper.on('click', '.pb-move-up', function () {
      const index = $(this).closest('.pb-schema-field').data('index');
      if (index > 0) {
        const [item] = schema.splice(index, 1);
        schema.splice(index - 1, 0, item);
        render();
      }
    });

    $fieldsWrapper.on('click', '.pb-move-down', function () {
      const index = $(this).closest('.pb-schema-field').data('index');
      if (index < schema.length - 1) {
        const [item] = schema.splice(index, 1);
        schema.splice(index + 1, 0, item);
        render();
      }
    });

    $fieldsWrapper.on('change', '.pb-field-type', function () {
      const $field = $(this).closest('.pb-schema-field');
      toggleOptions($field, $(this).val());
      syncTextarea();
    });

    $fieldsWrapper.on('input change', 'input, select, textarea', function () {
      const $field = $(this).closest('.pb-schema-field');
      if ($field.length && $(this).hasClass('pb-field-name')) {
        const sanitized = sanitizeName($(this).val());
        $(this).val(sanitized);
      }
      if ($field.length) {
        updatePlaceholderHint($field);
      }
      syncTextarea();
    });

    $fieldsWrapper.on('click', '.pb-add-option', function () {
      const $field = $(this).closest('.pb-schema-field');
      $field.find('.pb-schema-options-list').append(createOptionRow({ value: '', label: '' }));
      syncTextarea();
    });

    $fieldsWrapper.on('click', '.pb-remove-option', function () {
      $(this).closest('.pb-schema-option').remove();
      syncTextarea();
    });

    render();
  });
})(jQuery);
