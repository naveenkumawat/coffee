import { ApiEnvelope, get } from './client';
import { WebsiteContent } from '../types/content';

export function fetchWebsiteContent(): Promise<ApiEnvelope<WebsiteContent>> {
  return get<ApiEnvelope<WebsiteContent>>('/content');
}
