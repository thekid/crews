// Replacement for Sanitizer API, see https://web.dev/articles/sanitizer
// but also includes the possibility to rewrite tags to others.
class Markup {
  EXPAND = true;
  REMOVE = false;
  #handle = {
    'H1'       : {},
    'H2'       : {},
    'H3'       : {},
    'B'        : {},
    'I'        : {},
    'U'        : {},
    'P'        : {},
    'CODE'     : {},
    'PRE'      : {},
    'STRIKE'   : {},
    'STRONG'   : {emit: 'b'},
    'EM'       : {emit: 'i'},
    'SECTION'  : {emit: 'p'},
    'ARTICLE'  : {emit: 'p'},
    'A'        : {attributes: ['href']},
    'DIV'      : this.EXPAND,
    'SCRIPT'   : this.REMOVE,
    'EMBED'    : this.REMOVE,
    'OBJECT'   : this.REMOVE,
    'IFRAME'   : this.REMOVE,
    '#comment' : this.REMOVE,
    '#text'    : (n) => n.wholeText,
  };

  html(text) {
    return text.replace(/<>&'"/, m => `&#${m.charCodeAt(0)};`);
  }

  transform(nodes) {
    let transformed = '';
    for (const node of nodes) {
      // console.log(node.nodeName, node);

      const handle = this.#handle[node.nodeName] ?? null;
      if (null === handle) {
        transformed += this.html(node.innerText);
      } else if (this.REMOVE === handle) {
        // NOOP
      } else if (this.EXPAND === handle) {
        transformed += this.transform(node.childNodes);
      } else if ('function' === typeof handle) {
        transformed += handle(node);
      } else {
        const tag = handle.emit ?? node.nodeName;
        transformed += `<${tag}`;
        for (const attribute of handle.attributes ?? []) {
          if (node.hasAttribute(attribute)) {
            transformed += ` ${attribute}="${this.html(node.getAttribute(attribute))}"`;
          }
        }
        transformed += `>${this.transform(node.childNodes)}</${tag}>`;
      }
    }
    return transformed;
  }
}

class Editor {
  static markup = new Markup();

  constructor($node) {
    const $field = $node.querySelector('input[type="hidden"]');
    const $editable = $node.querySelector('div[contenteditable="true"]');

    for (const $button of $node.querySelectorAll('button')) {
      $button.addEventListener('click', e => {
        e.preventDefault();
        if ($button.dataset.command) {
          document.execCommand($button.dataset.command, false, $button.name);
        } else {
          document.execCommand($button.name, false, null);
        }
      });
    }

    // Synchronize hidden field
    $editable.addEventListener('input', e => {
      $field.value = $editable.innerHTML;
    })

    // Transform HTML, inserting text as-is. Todo: images, see
    // https://web.dev/patterns/clipboard/paste-images/
    $editable.addEventListener('paste', e => {
      console.log(e);
      e.preventDefault();
      if (-1 !== e.clipboardData.types.indexOf('text/html')) {
        const doc = document.implementation.createHTMLDocument('Pasted text');
        doc.write(e.clipboardData.getData('text/html'));
        document.execCommand('insertHTML', false, Editor.markup.transform(doc.body.childNodes));
      } else {
        document.execCommand('insertText', false, e.clipboardData.getData('text/plain'));
      }
    });
  }
}
