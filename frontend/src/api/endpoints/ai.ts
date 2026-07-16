import { api } from '../axios';

export interface AssistantMessage {
  role: 'user' | 'assistant';
  content: string;
}

export async function sendAssistantMessage(message: string, history: AssistantMessage[]): Promise<string> {
  const { data } = await api.post<{ reply: string }>('/ai/assistant/chat', { message, history });
  return data.reply;
}

export interface ParsedEmailFields {
  customer_name?: string;
  customer_email?: string;
  cargo_description?: string;
  origin_port?: string;
  destination_port?: string;
  mode?: 'sea' | 'air' | 'land';
  direction?: 'import' | 'export';
  notes?: string;
}

export async function parseEmail(emailText: string): Promise<ParsedEmailFields> {
  const { data } = await api.post<{ data: ParsedEmailFields }>('/ai/email-parser/parse', { email_text: emailText });
  return data.data;
}
