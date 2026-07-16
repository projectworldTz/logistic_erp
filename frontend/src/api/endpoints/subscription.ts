import { api } from '../axios';
import type { BillingProfile, Subscription, SubscriptionInvoice } from '../../types';

export async function fetchSubscription(): Promise<Subscription> {
  const { data } = await api.get<{ data: Subscription }>('/subscription');
  return data.data;
}

export async function fetchSubscriptionInvoices(): Promise<SubscriptionInvoice[]> {
  const { data } = await api.get<{ data: SubscriptionInvoice[] }>('/subscription/invoices');
  return data.data;
}

export async function changeSubscriptionPlan(payload: {
  plan_code: string;
  billing_cycle: 'monthly' | 'yearly';
}): Promise<Subscription> {
  const { data } = await api.put<{ data: Subscription }>('/subscription/plan', payload);
  return data.data;
}

export async function fetchBillingProfile(): Promise<BillingProfile> {
  const { data } = await api.get<{ data: BillingProfile }>('/billing-profile');
  return data.data;
}

export async function updateBillingProfile(payload: Partial<BillingProfile>): Promise<BillingProfile> {
  const { data } = await api.put<{ data: BillingProfile }>('/billing-profile', payload);
  return data.data;
}
