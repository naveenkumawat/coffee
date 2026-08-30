export interface FaqItem {
  id: string;
  question: string;
  answer: string;
}

/**
 * Parse CMS FAQ text into accordion items.
 * Expected blocks: "Question?\nAnswer" separated by blank lines.
 */
export function parseFaqItems(raw: string | null | undefined): FaqItem[] {
  const text = raw?.trim() ?? '';

  if (!text) {
    return [];
  }

  const blocks = text
    .split(/\n\s*\n/)
    .map((block) => block.trim())
    .filter(Boolean);

  const items: FaqItem[] = [];

  for (const [index, block] of blocks.entries()) {
    const lines = block.split('\n').map((line) => line.trim()).filter(Boolean);

    if (lines.length === 0) {
      continue;
    }

    const question = lines[0].replace(/^[QA][:.\-–—]\s*/i, '').trim();
    const answer = lines.slice(1).join('\n').replace(/^[QA][:.\-–—]\s*/i, '').trim();

    if (!answer) {
      continue;
    }

    items.push({
      id: `faq-${index + 1}`,
      question,
      answer,
    });
  }

  return items;
}

export function withRedirectQuery(path: string, redirect: string | null): string {
  if (!redirect) {
    return path;
  }

  return `${path}?redirect=${encodeURIComponent(redirect)}`;
}
