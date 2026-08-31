/**
 * Copy text to the clipboard with a secure-context API first, then a
 * legacy fallback for HTTP LAN / unsupported browsers.
 *
 * Never throws. Returns false for empty values or when both strategies fail.
 */
export async function copyTextToClipboard(value: string): Promise<boolean> {
  const text = value.trim();

  if (!text) {
    return false;
  }

  if (typeof navigator !== 'undefined' && typeof navigator.clipboard?.writeText === 'function') {
    try {
      await navigator.clipboard.writeText(text);

      return true;
    } catch {
      // Non-secure contexts (e.g. http://192.168.x.x) often reject Clipboard API.
    }
  }

  return copyWithExecCommand(text);
}

function copyWithExecCommand(text: string): boolean {
  if (typeof document === 'undefined' || typeof document.execCommand !== 'function') {
    return false;
  }

  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.setAttribute('readonly', '');
  textarea.setAttribute('aria-hidden', 'true');
  textarea.tabIndex = -1;
  textarea.style.position = 'fixed';
  textarea.style.top = '0';
  textarea.style.left = '0';
  textarea.style.width = '1px';
  textarea.style.height = '1px';
  textarea.style.padding = '0';
  textarea.style.border = '0';
  textarea.style.opacity = '0';
  textarea.style.pointerEvents = 'none';

  document.body.appendChild(textarea);

  const selection = document.getSelection();
  const previousRange = selection && selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

  textarea.focus();
  textarea.select();
  textarea.setSelectionRange(0, text.length);

  let copied = false;

  try {
    copied = document.execCommand('copy');
  } catch {
    copied = false;
  } finally {
    document.body.removeChild(textarea);

    if (selection) {
      selection.removeAllRanges();

      if (previousRange) {
        selection.addRange(previousRange);
      }
    }
  }

  return copied;
}
