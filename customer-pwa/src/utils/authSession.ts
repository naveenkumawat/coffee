let authenticated = false;

export function setSessionAuthenticated(value: boolean): void {
  authenticated = value;
}

export function isSessionAuthenticated(): boolean {
  return authenticated;
}
