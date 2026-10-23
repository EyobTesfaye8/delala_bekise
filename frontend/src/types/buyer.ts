export type BuyerRoute = 'my-jobs' | 'post-job' | 'messages' | 'escrows' | 'saved';

export type JobStatus = 'open' | 'negotiating' | 'contracted' | 'completed' | 'canceled';

export interface JobAttributeValue {
  [key: string]: string | number | boolean | null;
}

export interface BuyerJob {
  id: number;
  title: string;
  category_id: number;
  category_name: string;
  category_slug: string;
  location: string;
  location_region: string;
  location_subcity: string;
  budget_min_etb: number;
  budget_max_etb: number;
  status: JobStatus;
  proposal_count: number;
  created_at: string;
  attributes: JobAttributeValue;
  description: string;
}

export interface ProposalItem {
  id: number;
  proposal_id: number;
  item_name: string;
  description: string;
  text_location: string;
  image_urls: string[];
  estimated_price_etb: number;
  commission_fee_etb: number;
  status: 'pending' | 'accepted' | 'rejected';
  attributes?: Record<string, string | number | boolean>;
}

export interface DelalaProposal {
  id: number;
  job_id: number;
  delala_id: number;
  delala_name: string;
  delala_avatar: string;
  delala_phone: string;
  is_kyc_verified: boolean;
  rating: number;
  deals_completed: number;
  status: 'negotiating' | 'accepted' | 'rejected' | 'withdrawn';
  items: ProposalItem[];
  created_at: string;
}

export type EscrowState = 'FUNDED' | 'HANDOVER_DECLARED' | 'DISPUTED' | 'RELEASED';

export interface ActiveEscrowDeal {
  id: number;
  job_id: number;
  job_title: string;
  category_name: string;
  proposal_item_id: number;
  item_name: string;
  delala_id: number;
  delala_name: string;
  delala_phone: string;
  delala_avatar: string;
  commission_amount_etb: number;
  status: EscrowState;
  funded_at: string;
  handover_declared_at?: string;
  auto_release_deadline?: string; // 120h countdown
  cancellation_deadline?: string; // 48h countdown
  dispute_reason?: string;
  dispute_notes?: string;
  has_delala_receipt?: boolean;
}

export interface ChatMessage {
  id: number;
  chat_id: number;
  sender_type: 'buyer' | 'delala' | 'system';
  sender_name: string;
  text?: string;
  created_at: string;
  is_system?: boolean;
  system_event?: {
    type: 'commission_updated' | 'escrow_funded' | 'handover_declared';
    item_title: string;
    old_amount_etb?: number;
    new_amount_etb?: number;
    item_id?: number;
  };
}

export interface ChatThread {
  id: number;
  job_id: number;
  job_title: string;
  job_status: JobStatus;
  delala_id: number;
  delala_name: string;
  delala_phone: string;
  delala_avatar: string;
  is_kyc_verified: boolean;
  last_message: string;
  last_message_at: string;
  unread_count: number;
  is_escrow_funded: boolean;
}

export interface BookmarkedDelala {
  id: number;
  full_name: string;
  avatar: string;
  phone: string;
  is_kyc_verified: boolean;
  rating: number;
  review_count: number;
  deals_completed: number;
  categories: string[];
  location: string;
  bio: string;
}

export interface BuyerNotification {
  id: string;
  title: string;
  description: string;
  type: 'escrow' | 'proposal' | 'security';
  created_at: string;
  read: boolean;
  action_route?: BuyerRoute;
}
