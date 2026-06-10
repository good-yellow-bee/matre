import DOMPurify from 'dompurify';

const COLOR_MAP = {
  30: 'ansi-black', 31: 'ansi-red', 32: 'ansi-green', 33: 'ansi-yellow',
  34: 'ansi-blue', 35: 'ansi-magenta', 36: 'ansi-cyan', 37: 'ansi-white',
  90: 'ansi-bright-black', 91: 'ansi-bright-red', 92: 'ansi-bright-green',
  93: 'ansi-bright-yellow', 94: 'ansi-bright-blue', 95: 'ansi-bright-magenta',
  96: 'ansi-bright-cyan', 97: 'ansi-bright-white',
};

// Convert ANSI escape codes to HTML with styled spans (ported from legacy show.html.twig)
export function ansiToHtml(text) {
  if (!text) return text;

  const escaped = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');

  const openTags = [];

  // Match ANSI escape sequences: ESC[...m (both \x1b and bare [..m formats)
  let result = escaped.replace(/\x1b\[([0-9;]*)m|\[([0-9;]*)m/g, (match, p1, p2) => {
    const codes = (p1 || p2 || '0').split(';');
    let html = '';

    for (const code of codes) {
      if (code === '0' || code === '39' || code === '22') {
        while (openTags.length) {
          html += '</span>';
          openTags.pop();
        }
      } else if (code === '1') {
        html += '<span class="ansi-bold">';
        openTags.push('bold');
      } else if (COLOR_MAP[code]) {
        html += `<span class="${COLOR_MAP[code]}">`;
        openTags.push('color');
      }
    }

    return html;
  });

  while (openTags.length) {
    result += '</span>';
    openTags.pop();
  }

  return result;
}

export function ansiToSafeHtml(text) {
  return DOMPurify.sanitize(ansiToHtml(text) || '', {
    ALLOWED_TAGS: ['span'],
    ALLOWED_ATTR: ['class'],
  });
}
