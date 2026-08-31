import { useState } from 'react';
import { ApiError } from '../../api/client';
import { downloadOrderInvoice } from '../../api/orders';
import { Order } from '../../types/order';

interface DownloadInvoiceButtonProps {
  order: Order;
  className?: string;
}

export function DownloadInvoiceButton({ order, className = '' }: DownloadInvoiceButtonProps) {
  const [isDownloading, setIsDownloading] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  if (!order.invoice_available) {
    return null;
  }

  async function handleDownload(): Promise<void> {
    setIsDownloading(true);
    setErrorMessage(null);

    try {
      await downloadOrderInvoice(order.id, order.order_number);
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Unable to download invoice.');
    } finally {
      setIsDownloading(false);
    }
  }

  return (
    <div className={`order-invoice-actions ${className}`.trim()}>
      <button
        type="button"
        className="btn btn-outline-dark rounded-pill px-4"
        onClick={() => void handleDownload()}
        disabled={isDownloading}
      >
        {isDownloading ? 'Preparing PDF…' : 'Download Invoice'}
      </button>
      {errorMessage ? <p className="form-error-text">{errorMessage}</p> : null}
    </div>
  );
}
