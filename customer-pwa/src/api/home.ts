import { ApiEnvelope, get } from './client';
import { HomePayload } from '../types/home';

export function fetchHome(): Promise<ApiEnvelope<HomePayload>> {
  return get<ApiEnvelope<HomePayload>>('/home');
}
