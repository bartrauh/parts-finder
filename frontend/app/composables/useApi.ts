/**
 * $fetch preconfigured for the Laravel API: base URL and workshop token.
 */
export function useApi() {
  const config = useRuntimeConfig()
  // SSR runs inside the Docker network, the browser runs on the host
  const baseURL = import.meta.server ? config.apiBase : config.public.apiBase
  const token = import.meta.server ? config.apiToken : config.public.apiToken

  return $fetch.create({
    baseURL,
    headers: { Authorization: `Bearer ${token}` }
  })
}

/**
 * A readable message from an API error: the first validation errors, or the message.
 */
export function apiErrorMessage(error: unknown, fallback = 'Something went wrong, please try again.'): string {
  const data = (error as { data?: { message?: string, errors?: Record<string, string[]> } })?.data
  const errors = data?.errors ? Object.values(data.errors).flat() : []

  return errors.length ? errors.join(' ') : data?.message ?? fallback
}
