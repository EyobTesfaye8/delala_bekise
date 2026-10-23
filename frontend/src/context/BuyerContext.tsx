import React, { useState } from 'react';
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
import { BuyerContext } from './buyerContextDef';

// Initial Seed Data reflecting real Addis Ababa marketplace
const INITIAL_JOBS: BuyerJob[] = [
  {
    id: 1,
    title: 'Furnished 2BR Apartment near Bole Atlas',
    category_id: 2,
    category_name: 'Residential Rental',
    category_slug: 'residential-rental',
    location: 'Bole Atlas, Addis Ababa',
    location_region: 'Addis Ababa',
    location_subcity: 'Bole',
    budget_min_etb: 35000,
    budget_max_etb: 50000,
    status: 'negotiating',
    proposal_count: 4,
    created_at: '2 hours ago',
    description: 'Looking for a secure, modern 2-bedroom furnished apartment with 24/7 standby generator, underground parking, and fiber connectivity.',
    attributes: {
      bedrooms: 2,
      bathrooms: 2,
      furnished: true,
      backup_generator: true,
      parking: true,
    },
  },
  {
    id: 2,
    title: 'Toyota Vitz 2019 Automatic in Addis',
    category_id: 6,
    category_name: 'Passenger Cars',
    category_slug: 'passenger-cars',
    location: 'Kirkos / Bole, Addis Ababa',
    location_region: 'Addis Ababa',
    location_subcity: 'Kirkos',
    budget_min_etb: 1900000,
    budget_max_etb: 2400000,
    status: 'open',
    proposal_count: 2,
    created_at: 'Yesterday',
    description: 'Looking for a clean silver or white Toyota Vitz (2018-2020), automatic transmission, original factory paint, below 50k mileage.',
    attributes: {
      make: 'Toyota',
      model: 'Vitz',
      year: 2019,
      transmission: 'automatic',
      fuel_type: 'petrol',
    },
  },
  {
    id: 3,
    title: 'Prime 120sqm Commercial Office Suite',
    category_id: 3,
    category_name: 'Commercial Lease',
    category_slug: 'commercial-lease',
    location: 'Bole Road, Mega Building area',
    location_region: 'Addis Ababa',
    location_subcity: 'Bole',
    budget_min_etb: 60000,
    budget_max_etb: 90000,
    status: 'contracted',
    proposal_count: 3,
    created_at: '3 days ago',
    description: 'Ground floor or 1st floor space for fintech consulting firm. Must have elevator access and reliable transformer connection.',
    attributes: {
      property_type: 'Office',
      square_meters: 120,
      parking_spaces: 3,
      generator: true,
    },
  },
  {
    id: 4,
    title: 'Hyundai Tucson 2021 AWD Low Mileage',
    category_id: 6,
    category_name: 'Passenger Cars',
    category_slug: 'passenger-cars',
    location: 'Bole, Addis Ababa',
    location_region: 'Addis Ababa',
    location_subcity: 'Bole',
    budget_min_etb: 4500000,
    budget_max_etb: 5200000,
    status: 'completed',
    proposal_count: 5,
    created_at: '1 week ago',
    description: 'Original left-hand drive Hyundai Tucson, panorama roof, leather seats, strictly below 30,000 km.',
    attributes: {
      make: 'Hyundai',
      model: 'Tucson',
      year: 2021,
      transmission: 'automatic',
      drive_train: 'AWD',
    },
  },
];

const INITIAL_PROPOSALS: Record<number, DelalaProposal[]> = {
  1: [
    {
      id: 101,
      job_id: 1,
      delala_id: 13,
      delala_name: 'Abebe Kebede',
      delala_avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
      delala_phone: '+251 911 22 33 44',
      is_kyc_verified: true,
      rating: 4.9,
      deals_completed: 38,
      status: 'negotiating',
      created_at: '1 hour ago',
      items: [
        {
          id: 1001,
          proposal_id: 101,
          item_name: '2BR Furnished Atlas Residency with 24/7 Generator',
          description: 'Top floor sunny unit with high-speed wifi, underground parking spot, and German kitchen appliances. Owner is ready for inspection today.',
          text_location: 'Bole Atlas, 150m from Edna Mall',
          image_urls: [
            'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?w=600&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?w=600&auto=format&fit=crop&q=80',
            'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=600&auto=format&fit=crop&q=80',
          ],
          estimated_price_etb: 42000,
          commission_fee_etb: 16500,
          status: 'pending',
          attributes: {
            floor: 4,
            bedrooms: 2,
            bathrooms: 2,
            balcony: true,
          },
        },
        {
          id: 1002,
          proposal_id: 101,
          item_name: 'Modern Executive Suite at Bole Brass',
          description: 'Fully furnished with luxury imported furniture. Private standby silent generator dedicated to this block.',
          text_location: 'Bole Brass, near Japanese Embassy',
          image_urls: [
            'https://images.unsplash.com/photo-1502005229762-ae1b46b97063?w=600&auto=format&fit=crop&q=80',
          ],
          estimated_price_etb: 48000,
          commission_fee_etb: 18000,
          status: 'pending',
          attributes: {
            floor: 2,
            bedrooms: 2,
            bathrooms: 1.5,
          },
        },
      ],
    },
    {
      id: 102,
      job_id: 1,
      delala_id: 14,
      delala_name: 'Chala Bekele',
      delala_avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
      delala_phone: '+251 922 33 44 55',
      is_kyc_verified: true,
      rating: 4.7,
      deals_completed: 21,
      status: 'negotiating',
      created_at: '30 mins ago',
      items: [
        {
          id: 1003,
          proposal_id: 102,
          item_name: 'Contemporary 2BR Apartment behind Desalegn Hotel',
          description: 'Quiet residential neighborhood with double gate security. Water tank storage with automated pressure pump.',
          text_location: 'Bole Atlas, behind Desalegn Hotel',
          image_urls: [
            'https://images.unsplash.com/photo-1493809842364-78817add7ffb?w=600&auto=format&fit=crop&q=80',
          ],
          estimated_price_etb: 38000,
          commission_fee_etb: 15000,
          status: 'pending',
        },
      ],
    },
  ],
  2: [
    {
      id: 103,
      job_id: 2,
      delala_id: 13,
      delala_name: 'Abebe Kebede',
      delala_avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
      delala_phone: '+251 911 22 33 44',
      is_kyc_verified: true,
      rating: 4.9,
      deals_completed: 38,
      status: 'negotiating',
      created_at: '3 hours ago',
      items: [
        {
          id: 1004,
          proposal_id: 103,
          item_name: 'Toyota Vitz 2019 Pure Pearl White (Plate 3-B)',
          description: 'First owner in Ethiopia. Low genuine mileage of 39,200 km. Comprehensive technical check done at Nyala Motors.',
          text_location: 'Sarbet / Kirkos, Addis Ababa',
          image_urls: [
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=600&auto=format&fit=crop&q=80',
          ],
          estimated_price_etb: 2250000,
          commission_fee_etb: 25000,
          status: 'pending',
          attributes: {
            year: 2019,
            mileage_km: 39200,
            paint: 'Original Pearl White',
          },
        },
      ],
    },
  ],
};

const INITIAL_ESCROWS: ActiveEscrowDeal[] = [
  {
    id: 501,
    job_id: 3,
    job_title: 'Prime 120sqm Commercial Office Suite',
    category_name: 'Commercial Lease',
    proposal_item_id: 901,
    item_name: 'Level 2 Executive Office Suite in Mega Building',
    delala_id: 13,
    delala_name: 'Abebe Kebede',
    delala_phone: '+251 911 22 33 44',
    delala_avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
    commission_amount_etb: 16500,
    status: 'FUNDED',
    funded_at: '2026-10-22 14:30',
    cancellation_deadline: '2026-10-24 14:30', // 48h
    has_delala_receipt: false,
  },
  {
    id: 502,
    job_id: 1,
    job_title: 'Furnished 2BR Apartment near Bole Atlas',
    category_name: 'Residential Rental',
    proposal_item_id: 1001,
    item_name: '2BR Furnished Atlas Residency with 24/7 Generator',
    delala_id: 13,
    delala_name: 'Abebe Kebede',
    delala_phone: '+251 911 22 33 44',
    delala_avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
    commission_amount_etb: 16500,
    status: 'HANDOVER_DECLARED',
    funded_at: '2026-10-21 10:00',
    handover_declared_at: '2026-10-23 08:30',
    auto_release_deadline: new Date(Date.now() + 114 * 3600 * 1000 + 42 * 60 * 1000).toISOString(),
    has_delala_receipt: true,
  },
];

const INITIAL_CHATS: ChatThread[] = [
  {
    id: 1,
    job_id: 1,
    job_title: 'Furnished 2BR Apartment near Bole Atlas',
    job_status: 'negotiating',
    delala_id: 13,
    delala_name: 'Abebe Kebede',
    delala_phone: '+251 911 22 33 44',
    delala_avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
    is_kyc_verified: true,
    last_message: 'I updated the commission fee to 16,500 ETB as we agreed. You can inspect the unit today at 4 PM.',
    last_message_at: '12m ago',
    unread_count: 1,
    is_escrow_funded: false,
  },
  {
    id: 2,
    job_id: 3,
    job_title: 'Prime 120sqm Commercial Office Suite',
    job_status: 'contracted',
    delala_id: 13,
    delala_name: 'Abebe Kebede',
    delala_phone: '+251 911 22 33 44',
    delala_avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
    is_kyc_verified: true,
    last_message: 'Escrow funded! Meeting landlord on site at 2:00 PM tomorrow.',
    last_message_at: '2h ago',
    unread_count: 0,
    is_escrow_funded: true,
  },
  {
    id: 3,
    job_id: 2,
    job_title: 'Toyota Vitz 2019 Automatic in Addis',
    job_status: 'open',
    delala_id: 14,
    delala_name: 'Chala Bekele',
    delala_phone: '+251 922 33 44 55',
    delala_avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
    is_kyc_verified: true,
    last_message: 'The pearl white Vitz has full maintenance history at Nyala Motors.',
    last_message_at: 'Yesterday',
    unread_count: 0,
    is_escrow_funded: false,
  },
];

const INITIAL_MESSAGES: Record<number, ChatMessage[]> = {
  1: [
    {
      id: 1,
      chat_id: 1,
      sender_type: 'delala',
      sender_name: 'Abebe Kebede',
      text: 'Selam! I submitted 2 options for your Bole Atlas apartment request. The first one in Atlas Residency has a 24/7 dedicated generator.',
      created_at: '11:15 AM',
    },
    {
      id: 2,
      chat_id: 1,
      sender_type: 'buyer',
      sender_name: 'You',
      text: 'Thanks Abebe. Can you adjust the commission from 20,000 to 16,500 ETB? If so, I am ready to fund escrow right away.',
      created_at: '11:22 AM',
    },
    {
      id: 3,
      chat_id: 1,
      sender_type: 'system',
      sender_name: 'System',
      is_system: true,
      system_event: {
        type: 'commission_updated',
        item_title: '2BR Furnished Atlas Residency with 24/7 Generator',
        old_amount_etb: 20000,
        new_amount_etb: 16500,
        item_id: 1001,
      },
      created_at: '11:28 AM',
    },
    {
      id: 4,
      chat_id: 1,
      sender_type: 'delala',
      sender_name: 'Abebe Kebede',
      text: 'I updated the commission fee to 16,500 ETB as we agreed. You can inspect the unit today at 4 PM.',
      created_at: '11:29 AM',
    },
  ],
  2: [
    {
      id: 5,
      chat_id: 2,
      sender_type: 'system',
      sender_name: 'System',
      is_system: true,
      system_event: {
        type: 'escrow_funded',
        item_title: 'Level 2 Executive Office Suite in Mega Building',
        new_amount_etb: 16500,
      },
      created_at: 'Yesterday',
    },
    {
      id: 6,
      chat_id: 2,
      sender_type: 'delala',
      sender_name: 'Abebe Kebede',
      text: 'Escrow funded! Meeting landlord on site at 2:00 PM tomorrow. Please bring your trade license copy.',
      created_at: 'Yesterday',
    },
  ],
  3: [
    {
      id: 7,
      chat_id: 3,
      sender_type: 'delala',
      sender_name: 'Chala Bekele',
      text: 'The pearl white Vitz has full maintenance history at Nyala Motors.',
      created_at: 'Yesterday',
    },
  ],
};

const INITIAL_BOOKMARKS: BookmarkedDelala[] = [
  {
    id: 13,
    full_name: 'Abebe Kebede',
    avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&auto=format&fit=crop&q=80',
    phone: '+251 911 22 33 44',
    is_kyc_verified: true,
    rating: 4.9,
    review_count: 48,
    deals_completed: 38,
    categories: ['Residential Rental', 'Commercial Lease', 'Passenger Cars'],
    location: 'Bole & Kirkos, Addis Ababa',
    bio: 'Licensed broker with 8+ years experience in Bole, Old Airport, and Kazanchis prime real estate.',
  },
  {
    id: 14,
    full_name: 'Chala Bekele',
    avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&auto=format&fit=crop&q=80',
    phone: '+251 922 33 44 55',
    is_kyc_verified: true,
    rating: 4.7,
    review_count: 24,
    deals_completed: 21,
    categories: ['Passenger Cars', 'Commercial Vehicles'],
    location: 'Sarbet & Kera, Addis Ababa',
    bio: 'Specialist in inspected Toyota, Hyundai, and Suzuki passenger vehicles with verified customs clearance.',
  },
];

const INITIAL_NOTIFICATIONS: BuyerNotification[] = [
  {
    id: 'n1',
    title: 'Handover Declared by Delala Abebe',
    description: 'Broker declared key handover for Bole Atlas 2BR. 120-hour auto-release countdown started.',
    type: 'escrow',
    created_at: '25m ago',
    read: false,
    action_route: 'escrows',
  },
  {
    id: 'n2',
    title: 'Commission Fee Discounted',
    description: 'Delala Abebe updated commission from 20,000 to 16,500 ETB.',
    type: 'proposal',
    created_at: '1h ago',
    read: false,
    action_route: 'messages',
  },
  {
    id: 'n3',
    title: 'Anti-Circumvention Protection Active',
    description: 'Never send offline deposits. Escrow ensures 100% money-back guarantee.',
    type: 'security',
    created_at: 'Yesterday',
    read: true,
    action_route: 'my-jobs',
  },
];

export const BuyerProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [currentRoute, setCurrentRouteState] = useState<BuyerRoute>('my-jobs');
  const [selectedJobId, setSelectedJobId] = useState<number | null>(null);
  const [selectedChatId, setSelectedChatId] = useState<number | null>(1);
  const [isGuestMode, setIsGuestMode] = useState<boolean>(false);
  const [activeEscrowPreviewState, setActiveEscrowPreviewState] = useState<EscrowState>('HANDOVER_DECLARED');

  const [jobs, setJobs] = useState<BuyerJob[]>(INITIAL_JOBS);
  const [proposalsByJobId, setProposalsByJobId] = useState<Record<number, DelalaProposal[]>>(INITIAL_PROPOSALS);
  const [escrows, setEscrows] = useState<ActiveEscrowDeal[]>(INITIAL_ESCROWS);
  const [chatThreads, setChatThreads] = useState<ChatThread[]>(INITIAL_CHATS);
  const [chatMessages, setChatMessages] = useState<Record<number, ChatMessage[]>>(INITIAL_MESSAGES);
  const [bookmarkedDelalas] = useState<BookmarkedDelala[]>(INITIAL_BOOKMARKS);
  const [notifications, setNotifications] = useState<BuyerNotification[]>(INITIAL_NOTIFICATIONS);

  // Sync route and subview state
  const setRoute = (route: BuyerRoute, jobId: number | null = null) => {
    setCurrentRouteState(route);
    setSelectedJobId(jobId);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const openProposalInbox = (jobId: number) => {
    setSelectedJobId(jobId);
    setCurrentRouteState('my-jobs');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const openChatForJob = (jobId: number, delalaId?: number) => {
    // Find or create chat thread
    const thread = chatThreads.find(
      (t) => t.job_id === jobId && (!delalaId || t.delala_id === delalaId)
    );
    if (thread) {
      setSelectedChatId(thread.id);
    }
    setCurrentRouteState('messages');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  const toggleGuestMode = () => {
    setIsGuestMode((prev) => !prev);
  };

  const setEscrowPreviewState = (state: EscrowState) => {
    setActiveEscrowPreviewState(state);
    setEscrows((prev) =>
      prev.map((escrow, idx) => (idx === 1 ? { ...escrow, status: state } : escrow))
    );
  };

  const createJob = (newJobData: Omit<BuyerJob, 'id' | 'created_at' | 'proposal_count'>) => {
    const newJob: BuyerJob = {
      ...newJobData,
      id: Date.now(),
      created_at: 'Just now',
      proposal_count: 0,
      status: 'open',
    };
    setJobs((prev) => [newJob, ...prev]);
    setRoute('my-jobs');
  };

  const cloneJob = (jobId: number) => {
    const sourceJob = jobs.find((j) => j.id === jobId);
    if (!sourceJob) return;

    const cloned: BuyerJob = {
      ...sourceJob,
      id: Date.now(),
      title: `${sourceJob.title} (Re-posted)`,
      status: 'open',
      proposal_count: 0,
      created_at: 'Just now',
    };
    setJobs((prev) => [cloned, ...prev]);
    setRoute('my-jobs');
  };

  const acceptAndFundItem = (
    jobId: number,
    proposalId: number,
    itemId: number,
    amountEtb: number
  ) => {
    // Mark item accepted
    setProposalsByJobId((prev) => {
      const proposals = prev[jobId] || [];
      const updated = proposals.map((p) => {
        if (p.id === proposalId) {
          return {
            ...p,
            status: 'accepted' as const,
            items: p.items.map((i) =>
              i.id === itemId ? { ...i, status: 'accepted' as const } : i
            ),
          };
        }
        return p;
      });
      return { ...prev, [jobId]: updated };
    });

    // Update job status to contracted
    setJobs((prev) =>
      prev.map((j) => (j.id === jobId ? { ...j, status: 'contracted' as const } : j))
    );

    // Create escrow deal
    const proposal = proposalsByJobId[jobId]?.find((p) => p.id === proposalId);
    const item = proposal?.items.find((i) => i.id === itemId);
    const job = jobs.find((j) => j.id === jobId);

    if (proposal && item && job) {
      const newEscrow: ActiveEscrowDeal = {
        id: Date.now(),
        job_id: jobId,
        job_title: job.title,
        category_name: job.category_name,
        proposal_item_id: itemId,
        item_name: item.item_name,
        delala_id: proposal.delala_id,
        delala_name: proposal.delala_name,
        delala_phone: proposal.delala_phone,
        delala_avatar: proposal.delala_avatar,
        commission_amount_etb: amountEtb,
        status: 'FUNDED',
        funded_at: 'Just now',
        cancellation_deadline: new Date(Date.now() + 48 * 3600 * 1000).toISOString(),
        has_delala_receipt: false,
      };
      setEscrows((prev) => [newEscrow, ...prev]);

      // Add system message to chat
      const thread = chatThreads.find((t) => t.job_id === jobId);
      if (thread) {
        setChatMessages((prev) => ({
          ...prev,
          [thread.id]: [
            ...(prev[thread.id] || []),
            {
              id: Date.now(),
              chat_id: thread.id,
              sender_type: 'system',
              sender_name: 'Escrow System',
              is_system: true,
              system_event: {
                type: 'escrow_funded',
                item_title: item.item_name,
                new_amount_etb: amountEtb,
              },
              created_at: 'Just now',
            },
          ],
        }));
      }
    }

    setRoute('escrows');
  };

  const rejectProposalItem = (jobId: number, proposalId: number, itemId: number) => {
    setProposalsByJobId((prev) => {
      const proposals = prev[jobId] || [];
      const updated = proposals.map((p) => {
        if (p.id === proposalId) {
          return {
            ...p,
            items: p.items.map((i) =>
              i.id === itemId ? { ...i, status: 'rejected' as const } : i
            ),
          };
        }
        return p;
      });
      return { ...prev, [jobId]: updated };
    });
  };

  const confirmHandover = (_rating: number, _feedback: string) => {
    setEscrows((prev) =>
      prev.map((e) =>
        e.status === 'HANDOVER_DECLARED' ? { ...e, status: 'RELEASED' as const } : e
      )
    );
  };

  const submitDispute = (reason: string, notes: string) => {
    setEscrows((prev) =>
      prev.map((e) =>
        e.status === 'HANDOVER_DECLARED'
          ? {
              ...e,
              status: 'DISPUTED' as const,
              dispute_reason: reason,
              dispute_notes: notes,
            }
          : e
      )
    );
  };

  const requestCancellation = (escrowId: number) => {
    setEscrows((prev) =>
      prev.map((e) =>
        e.id === escrowId
          ? {
              ...e,
              cancellation_deadline: new Date(Date.now() + 48 * 3600 * 1000).toISOString(),
            }
          : e
      )
    );
  };

  const sendMessage = (chatId: number, text: string): boolean => {
    // Anti-circumvention detection: phone numbers, telegram, emails
    const phonePattern = /(\+?251|0)?[97]\d{8}/;
    const telegramPattern = /@\w{4,}/;
    const emailPattern = /[\w.-]+@[\w.-]+\.\w+/;

    const thread = chatThreads.find((t) => t.id === chatId);
    const isFunded = thread?.is_escrow_funded ?? false;

    if (!isFunded && (phonePattern.test(text) || telegramPattern.test(text) || emailPattern.test(text))) {
      return false; // Violates anti-circumvention policy
    }

    const newMsg: ChatMessage = {
      id: Date.now(),
      chat_id: chatId,
      sender_type: 'buyer',
      sender_name: 'You',
      text,
      created_at: 'Just now',
    };

    setChatMessages((prev) => ({
      ...prev,
      [chatId]: [...(prev[chatId] || []), newMsg],
    }));

    setChatThreads((prev) =>
      prev.map((t) => (t.id === chatId ? { ...t, last_message: text, last_message_at: 'Just now' } : t))
    );

    return true;
  };

  const markNotificationsAsRead = () => {
    setNotifications((prev) => prev.map((n) => ({ ...n, read: true })));
  };

  const upgradeGuestAccount = (_email: string) => {
    setIsGuestMode(false);
  };

  const unreadNotificationCount = notifications.filter((n) => !n.read).length;
  const unreadMessageCount = chatThreads.reduce((acc, t) => acc + t.unread_count, 0);
  const activeEscrowCount = escrows.filter(
    (e) => e.status === 'FUNDED' || e.status === 'HANDOVER_DECLARED' || e.status === 'DISPUTED'
  ).length;

  return (
    <BuyerContext.Provider
      value={{
        currentRoute,
        selectedJobId,
        selectedChatId,
        isGuestMode,
        activeEscrowPreviewState,
        jobs,
        proposalsByJobId,
        escrows,
        chatThreads,
        chatMessages,
        bookmarkedDelalas,
        notifications,
        unreadNotificationCount,
        unreadMessageCount,
        activeEscrowCount,
        setRoute,
        openProposalInbox,
        openChatForJob,
        toggleGuestMode,
        setEscrowPreviewState,
        createJob,
        cloneJob,
        acceptAndFundItem,
        rejectProposalItem,
        confirmHandover,
        submitDispute,
        requestCancellation,
        sendMessage,
        markNotificationsAsRead,
        upgradeGuestAccount,
      }}
    >
      {children}
    </BuyerContext.Provider>
  );
};

