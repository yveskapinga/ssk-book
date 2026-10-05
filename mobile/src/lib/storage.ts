import AsyncStorage from '@react-native-async-storage/async-storage';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

const TOKEN_KEY = 'ssk-book.access-token';
const USER_KEY = 'ssk-book.user';

export type StoredUser = {
  id: string;
  email: string;
  displayName: string;
  roles: string[];
  status: string;
};

async function secureGet(key: string): Promise<string | null> {
  if (Platform.OS === 'web') return AsyncStorage.getItem(key);
  return SecureStore.getItemAsync(key);
}

async function secureSet(key: string, value: string | null): Promise<void> {
  if (Platform.OS === 'web') {
    if (value) await AsyncStorage.setItem(key, value);
    else await AsyncStorage.removeItem(key);
    return;
  }
  if (value) await SecureStore.setItemAsync(key, value);
  else await SecureStore.deleteItemAsync(key);
}

let memoryToken: string | null = null;
let memoryUser: StoredUser | null = null;
let hydrated = false;

export async function hydrateSession(): Promise<void> {
  if (hydrated) return;
  memoryToken = await secureGet(TOKEN_KEY);
  const raw = await AsyncStorage.getItem(USER_KEY);
  if (raw) {
    try {
      memoryUser = JSON.parse(raw) as StoredUser;
    } catch {
      memoryUser = null;
    }
  }
  hydrated = true;
}

export function getToken(): string | null {
  return memoryToken;
}

export function getStoredUser(): StoredUser | null {
  return memoryUser;
}

export async function setSession(token: string, user: StoredUser): Promise<void> {
  memoryToken = token;
  memoryUser = user;
  await secureSet(TOKEN_KEY, token);
  await AsyncStorage.setItem(USER_KEY, JSON.stringify(user));
}

export async function clearSession(): Promise<void> {
  memoryToken = null;
  memoryUser = null;
  await secureSet(TOKEN_KEY, null);
  await AsyncStorage.removeItem(USER_KEY);
}

export async function setStoredUser(user: StoredUser): Promise<void> {
  memoryUser = user;
  await AsyncStorage.setItem(USER_KEY, JSON.stringify(user));
}
