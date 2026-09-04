import { ApiEnvelope, destroy, get, post, put } from './client';

export interface DeliveryAddress {
  id: number;
  label: string | null;
  recipient_name: string;
  phone: string;
  address_line_1: string;
  address_line_2: string | null;
  landmark: string | null;
  city: string;
  state: string;
  postal_code: string;
  is_default: boolean;
  formatted_address: string;
}

export type DeliveryAddressPayload = {
  label?: string | null;
  recipient_name: string;
  phone: string;
  address_line_1: string;
  address_line_2?: string | null;
  landmark?: string | null;
  city: string;
  state: string;
  postal_code: string;
  is_default?: boolean;
};

export function fetchDeliveryAddresses(): Promise<ApiEnvelope<DeliveryAddress[]>> {
  return get<ApiEnvelope<DeliveryAddress[]>>('/account/delivery-addresses');
}

export function createDeliveryAddress(payload: DeliveryAddressPayload): Promise<ApiEnvelope<DeliveryAddress>> {
  return post<ApiEnvelope<DeliveryAddress>, DeliveryAddressPayload>('/account/delivery-addresses', payload);
}

export function updateDeliveryAddress(
  id: number,
  payload: DeliveryAddressPayload,
): Promise<ApiEnvelope<DeliveryAddress>> {
  return put<ApiEnvelope<DeliveryAddress>, DeliveryAddressPayload>(`/account/delivery-addresses/${id}`, payload);
}

export function deleteDeliveryAddress(id: number): Promise<ApiEnvelope<null>> {
  return destroy<ApiEnvelope<null>>(`/account/delivery-addresses/${id}`);
}

export function makeDefaultDeliveryAddress(id: number): Promise<ApiEnvelope<DeliveryAddress>> {
  return post<ApiEnvelope<DeliveryAddress>, Record<string, never>>(`/account/delivery-addresses/${id}/default`, {});
}
