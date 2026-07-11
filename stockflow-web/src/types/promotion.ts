export type PromotionDiscountType =
  | 'percentage'
  | 'fixed'

export type PromotionStatus =
  | 'active'
  | 'upcoming'
  | 'expired'
  | 'inactive'

export interface PromotionCreator {
  id: number
  name: string
}

export interface PromotionItem {
  id: number

  name: string
  code: string

  discount_type: PromotionDiscountType
  discount_value: number

  minimum_purchase: number
  maximum_discount: number | null

  starts_at: string
  ends_at: string

  is_active: boolean

  status: PromotionStatus

  is_currently_available: boolean

  creator: PromotionCreator

  created_at: string | null
  updated_at: string | null
}

export interface PromotionFilters {
  search: string

  discount_type:
    | ''
    | PromotionDiscountType

  status:
    | ''
    | PromotionStatus

  page: number
  per_page: number
}

export interface SavePromotionPayload {
  name: string
  code: string

  discount_type: PromotionDiscountType
  discount_value: number

  minimum_purchase: number
  maximum_discount: number | null

  starts_at: string
  ends_at: string

  is_active: boolean
}

export interface PromotionPaginationMeta {
  current_page: number
  from: number | null
  last_page: number
  per_page: number
  to: number | null
  total: number
}

export interface PaginatedPromotionsResponse {
  data: PromotionItem[]

  links: {
    first: string | null
    last: string | null
    prev: string | null
    next: string | null
  }

  meta: PromotionPaginationMeta
}

export interface PromotionMutationResponse {
  message: string
  promotion: PromotionItem
}

export interface AvailablePromotionsResponse {
  data: PromotionItem[]
}

export type PromotionValidationErrors =
  Record<string, string[]>