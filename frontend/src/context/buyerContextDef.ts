import { createContext } from 'react';
import type {
  BuyerRoute,
  BuyerJob,
  DelalaProposal,
  ActiveEscrowDeal,
  ChatThread,
  ChatMessage,
  BookmarkedDelala,
  BuyerNotification,
  EscrowState,
} from '../types/buyer';

export interface BuyerContextType {
  currentRoute: BuyerRoute;
  selectedJobId: number | null;
  selectedChatId: number | null;
  isGuestMode: boolean;
  activeEscrowPreviewState: EscrowState;
  jobs: BuyerJob[];
  proposalsByJobId: Record<number, DelalaProposal[]>;
  escrows: ActiveEscrowDeal[];
  chatThreads: ChatThread[];
  chatMessages: Record<number, ChatMessage[]>;
  bookmarkedDelalas: BookmarkedDelala[];
  notifications: BuyerNotification[];
  unreadNotificationCount: number;
  unreadMessageCount: number;
  activeEscrowCount: number;
  setRoute: (route: BuyerRoute, jobId?: number | null) => void;
  openProposalInbox: (jobId: number) => void;
  openChatForJob: (jobId: number, delalaId?: number) => void;
  toggleGuestMode: () => void;
  setEscrowPreviewState: (state: EscrowState) => void;
  createJob: (newJob: Omit<BuyerJob, 'id' | 'created_at' | 'proposal_count'>) => void;
  cloneJob: (jobId: number) => void;
  acceptAndFundItem: (jobId: number, proposalId: number, itemId: number, amountEtb: number) => void;
  rejectProposalItem: (jobId: number, proposalId: number, itemId: number) => void;
  confirmHandover: (rating: number, feedback: string) => void;
  submitDispute: (reason: string, notes: string) => void;
  requestCancellation: (escrowId: number) => void;
  sendMessage: (chatId: number, text: string) => boolean;
  markNotificationsAsRead: () => void;
  upgradeGuestAccount: (email: string) => void;
}

export const BuyerContext = createContext<BuyerContextType | undefined>(undefined);
