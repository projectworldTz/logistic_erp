import { api } from '../axios';
import type { ComplianceDocument, ComplianceDocumentType, Contact, Customer, CustomerMessage, Lead, Paginated } from '../../types';

export async function fetchLeads(page = 1): Promise<Paginated<Lead>> {
  const { data } = await api.get<Paginated<Lead>>('/crm/leads', { params: { page } });
  return data;
}

export async function createLead(payload: Partial<Lead>): Promise<Lead> {
  const { data } = await api.post<{ data: Lead }>('/crm/leads', payload);
  return data.data;
}

export async function updateLead(id: number, payload: Partial<Lead>): Promise<Lead> {
  const { data } = await api.put<{ data: Lead }>(`/crm/leads/${id}`, payload);
  return data.data;
}

export async function deleteLead(id: number): Promise<void> {
  await api.delete(`/crm/leads/${id}`);
}

export async function convertLead(id: number): Promise<Customer> {
  const { data } = await api.post<{ data: Customer }>(`/crm/leads/${id}/convert`);
  return data.data;
}

export async function fetchCustomers(page = 1): Promise<Paginated<Customer>> {
  const { data } = await api.get<Paginated<Customer>>('/crm/customers', { params: { page } });
  return data;
}

export async function fetchCustomer(id: number): Promise<Customer> {
  const { data } = await api.get<{ data: Customer }>(`/crm/customers/${id}`);
  return data.data;
}

export async function createCustomer(payload: Partial<Customer>): Promise<Customer> {
  const { data } = await api.post<{ data: Customer }>('/crm/customers', payload);
  return data.data;
}

export async function updateCustomer(id: number, payload: Partial<Customer>): Promise<Customer> {
  const { data } = await api.put<{ data: Customer }>(`/crm/customers/${id}`, payload);
  return data.data;
}

export async function deleteCustomer(id: number): Promise<void> {
  await api.delete(`/crm/customers/${id}`);
}

export async function createContact(customerId: number, payload: Partial<Contact>): Promise<Contact> {
  const { data } = await api.post<{ data: Contact }>(`/crm/customers/${customerId}/contacts`, payload);
  return data.data;
}

export async function updateContact(customerId: number, contactId: number, payload: Partial<Contact>): Promise<Contact> {
  const { data } = await api.put<{ data: Contact }>(`/crm/customers/${customerId}/contacts/${contactId}`, payload);
  return data.data;
}

export async function deleteContact(customerId: number, contactId: number): Promise<void> {
  await api.delete(`/crm/customers/${customerId}/contacts/${contactId}`);
}

export async function fetchCustomerMessages(customerId: number): Promise<{ data: CustomerMessage[] }> {
  const { data } = await api.get<{ data: CustomerMessage[] }>(`/crm/customers/${customerId}/messages`);
  return data;
}

export async function sendCustomerMessage(customerId: number, body: string): Promise<CustomerMessage> {
  const { data } = await api.post<{ data: CustomerMessage }>(`/crm/customers/${customerId}/messages`, { body });
  return data.data;
}

export async function fetchComplianceDocuments(customerId: number): Promise<ComplianceDocument[]> {
  const { data } = await api.get<{ data: ComplianceDocument[] }>(`/crm/customers/${customerId}/compliance-documents`);
  return data.data;
}

export interface ComplianceDocumentPayload {
  document_type: ComplianceDocumentType;
  document_number?: string;
  issue_date?: string;
  expiry_date?: string;
  file?: File | null;
  notes?: string;
}

export async function createComplianceDocument(customerId: number, payload: ComplianceDocumentPayload): Promise<ComplianceDocument> {
  const form = new FormData();
  form.append('document_type', payload.document_type);
  if (payload.document_number) form.append('document_number', payload.document_number);
  if (payload.issue_date) form.append('issue_date', payload.issue_date);
  if (payload.expiry_date) form.append('expiry_date', payload.expiry_date);
  if (payload.file) form.append('file', payload.file);
  if (payload.notes) form.append('notes', payload.notes);

  const { data } = await api.post<{ data: ComplianceDocument }>(`/crm/customers/${customerId}/compliance-documents`, form, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });
  return data.data;
}

export async function deleteComplianceDocument(customerId: number, documentId: number): Promise<void> {
  await api.delete(`/crm/customers/${customerId}/compliance-documents/${documentId}`);
}
