/*
 * Minimum length check for the search forms.
 *
 * Each checked text field needs at least MIN_CHARS characters, not counting wildcards (* % _) or
 * whitespace.  If a field is too short, a message is shown in a warning box under the form's buttons
 * and the search is not run.  The same rule is enforced on the server, see search_validation.php.
 *
 * SearchValidation.attach({
 *     form:   'id of the form',
 *     box:    'id of the element that shows the message',
 *     fields: { fieldname: 'Label' },    // check only these fields, or
 *     except: { fieldname: true },       // check every text field except these
 *     labels: { fieldname: 'Label' },    // labels to use in the message (default: the text beside the field)
 *     ignore: '"'                        // other characters that don't count, optional
 * });
 */
var SearchValidation = (function () {
  var MIN_CHARS = 2;

  function significantLength(value, ignore) {
    var extra = (ignore || '').replace(/[\\\]\^\-]/g, '\\$&');
    return value.replace(new RegExp('[\\s*%_' + extra + ']+', 'g'), '').length;
  }

  // Label of a field: the text of the table cell to the left of the cell that holds the field.
  function labelFor(input, labels) {
    if (labels && labels[input.name]) { return labels[input.name]; }
    var cell = input.parentNode;
    while (cell && cell.nodeName !== 'TD') { cell = cell.parentNode; }
    var prev = cell ? cell.previousElementSibling : null;
    while (prev && !/\S/.test(prev.textContent)) { prev = prev.previousElementSibling; }
    return prev ? prev.textContent.replace(/\s+/g, ' ').replace(/^ | $/g, '') : input.name;
  }

  function shortFields(form, options) {
    var bad = [];
    // form.elements, not getElementsByTagName: controls placed in a table can belong to the form
    // without being nested inside it.
    for (var i = 0; i < form.elements.length; i++) {
      var field = form.elements[i];
      if (field.type !== 'text') { continue; }
      if (options.fields ? !options.fields[field.name] : (options.except && options.except[field.name])) { continue; }
      if (/\S/.test(field.value) && significantLength(field.value, options.ignore) < MIN_CHARS) {
        bad.push(labelFor(field, options.fields || options.labels));
      }
    }
    return bad;
  }

  function showWarning(box, labels) {
    while (box.firstChild) { box.removeChild(box.firstChild); }
    box.appendChild(document.createTextNode('Please enter at least ' + MIN_CHARS + ' characters, not counting wildcards, in: '));
    var list = document.createElement('strong');
    list.appendChild(document.createTextNode(labels.join(', ')));
    box.appendChild(list);
    box.appendChild(document.createTextNode('.'));
    box.style.display = 'block';
  }

  function attach(options) {
    var form = document.getElementById(options.form);
    var box = document.getElementById(options.box);
    if (!form || !box || !form.addEventListener) { return; }
    form.addEventListener('submit', function (event) {
      var bad = shortFields(form, options);
      if (bad.length > 0) {
        event.preventDefault();
        showWarning(box, bad);
      } else {
        box.style.display = 'none';
      }
    }, false);
    form.addEventListener('reset', function () { box.style.display = 'none'; }, false);
  }

  return { attach: attach };
})();
