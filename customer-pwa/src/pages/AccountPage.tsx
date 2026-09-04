import { FormEvent, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { updateCustomerPassword, updateCustomerProfile } from '../api/account';
import { fetchLoyalty } from '../api/loyalty';
import { ApiError, ApiValidationErrors } from '../api/client';
import { AccountMenuItem } from '../components/account/AccountMenuItem';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { useNotificationStore } from '../stores/notificationStore';
import { useToastStore } from '../stores/toastStore';
import { AppIcons } from '../utils/icons';
import { getFieldError } from '../utils/forms';

export function AccountPage() {
  const customer = useAuthStore((state) => state.customer);
  const syncCustomer = useAuthStore((state) => state.syncCustomer);
  const logout = useAuthStore((state) => state.logout);
  const toastSuccess = useToastStore((state) => state.success);
  const navigate = useNavigate();
  const unreadCount = useNotificationStore((state) => state.unreadCount);
  const [profileForm, setProfileForm] = useState({
    name: '',
    email: '',
    phone: '',
  });
  const [passwordForm, setPasswordForm] = useState({
    current_password: '',
    password: '',
    password_confirmation: '',
  });
  const [profileErrors, setProfileErrors] = useState<ApiValidationErrors>({});
  const [passwordErrors, setPasswordErrors] = useState<ApiValidationErrors>({});
  const [profileMessage, setProfileMessage] = useState<string | null>(null);
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null);
  const [profileMessageVariant, setProfileMessageVariant] = useState<'success' | 'error'>('success');
  const [passwordMessageVariant, setPasswordMessageVariant] = useState<'success' | 'error'>('success');
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [isSavingPassword, setIsSavingPassword] = useState(false);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [loyaltyPointsLabel, setLoyaltyPointsLabel] = useState<string | null>(null);
  const [profileOpen, setProfileOpen] = useState(false);
  const [passwordOpen, setPasswordOpen] = useState(false);

  useEffect(() => {
    let cancelled = false;

    void fetchLoyalty()
      .then((response) => {
        if (!cancelled) {
          const points = response.data.display_available_points ?? Math.max(0, response.data.available_points);
          setLoyaltyPointsLabel(`${points} points`);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setLoyaltyPointsLabel(null);
        }
      });

    return () => {
      cancelled = true;
    };
  }, []);

  useEffect(() => {
    if (!customer) {
      return;
    }

    setProfileForm({
      name: customer.name,
      email: customer.email,
      phone: customer.phone ?? '',
    });
  }, [customer]);

  async function handleProfileSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSavingProfile(true);
    setProfileErrors({});
    setProfileMessage(null);
    setProfileMessageVariant('success');

    try {
      const response = await updateCustomerProfile({
        ...profileForm,
        phone: profileForm.phone.trim() || null,
      });
      syncCustomer(response.data);
      const message = response.message ?? 'Profile updated.';
      setProfileMessage(message);
      toastSuccess(message);
      setProfileOpen(false);
    } catch (error) {
      setProfileOpen(true);
      if (error instanceof ApiError) {
        setProfileErrors(error.errors);
        setProfileMessage(error.message);
        setProfileMessageVariant('error');
      } else {
        setProfileMessage('Unable to update your profile right now.');
        setProfileMessageVariant('error');
      }
    } finally {
      setIsSavingProfile(false);
    }
  }

  async function handlePasswordSubmit(event: FormEvent<HTMLFormElement>): Promise<void> {
    event.preventDefault();
    setIsSavingPassword(true);
    setPasswordErrors({});
    setPasswordMessage(null);
    setPasswordMessageVariant('success');

    try {
      const response = await updateCustomerPassword(passwordForm);
      syncCustomer(response.data);
      setPasswordForm({
        current_password: '',
        password: '',
        password_confirmation: '',
      });
      const message = response.message ?? 'Password updated.';
      setPasswordMessage(message);
      toastSuccess(message);
      setPasswordOpen(false);
    } catch (error) {
      setPasswordOpen(true);
      if (error instanceof ApiError) {
        setPasswordErrors(error.errors);
        setPasswordMessage(error.message);
        setPasswordMessageVariant('error');
      } else {
        setPasswordMessage('Unable to update your password right now.');
        setPasswordMessageVariant('error');
      }
    } finally {
      setIsSavingPassword(false);
    }
  }

  async function handleLogout(): Promise<void> {
    setIsLoggingOut(true);

    try {
      await logout();
      navigate('/', { replace: true });
    } finally {
      setIsLoggingOut(false);
    }
  }

  if (!customer) {
    return (
      <div className="page-container">
        <h1 className="visually-hidden">Account</h1>
        <LoadingSkeleton cardCount={2} lines={3} />
      </div>
    );
  }

  const firstName = customer.name.split(' ')[0] || customer.name;

  return (
    <div className="page-container account-page">
      <h1 className="visually-hidden">Account</h1>

      <section className="account-hero account-hero-clean motion-enter">
        <p className="eyebrow">Signed in</p>
        <h2>Hi, {firstName}</h2>
        <p>{customer.email}</p>
        {customer.phone ? <p>{customer.phone}</p> : <p>Add a phone number for easier pickup updates.</p>}
      </section>

      <section className="account-section account-shortcuts-primary">
        <div className="account-link-list">
          <AccountMenuItem to="/orders" label="My Orders" icon="orders" />
          <AccountMenuItem
            to="/account/notifications"
            label="Notifications"
            icon="notification"
            badgeCount={unreadCount}
          />
          <AccountMenuItem
            to="/account/loyalty"
            label="Rewards"
            icon="rewards"
            meta={loyaltyPointsLabel}
          />
          <AccountMenuItem to="/favourites" label="Favourites" icon="favourite" />
          <AccountMenuItem to="/account/delivery-addresses" label="Delivery Addresses" icon="addresses" />
          <AccountMenuItem to="/account/referral" label="Refer a Friend" icon="referral" />
        </div>
      </section>

      <section className="account-section account-accordion-list">
        <div className="account-accordion">
          <button
            type="button"
            className="account-accordion-trigger"
            aria-expanded={profileOpen}
            aria-controls="account-profile-panel"
            id="account-profile-trigger"
            onClick={() => setProfileOpen((open) => !open)}
          >
            <span>
              <i className={`bi ${AppIcons.edit}`} aria-hidden="true"></i>
              Personal details
            </span>
            <i className={`bi ${profileOpen ? AppIcons.chevronDown : AppIcons.chevronRight}`} aria-hidden="true"></i>
          </button>
          {profileOpen ? (
            <div
              id="account-profile-panel"
              role="region"
              aria-labelledby="account-profile-trigger"
              className="account-accordion-panel"
            >
              <FormFeedback message={profileMessage} variant={profileMessageVariant} />
              <form className="auth-form account-form" onSubmit={(event) => void handleProfileSubmit(event)}>
                <FormField
                  label="Full name"
                  name="name"
                  autoComplete="name"
                  value={profileForm.name}
                  onChange={(event) => setProfileForm((currentValue) => ({ ...currentValue, name: event.target.value }))}
                  error={getFieldError(profileErrors, 'name')}
                  required
                />
                <FormField
                  label="Email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  inputMode="email"
                  value={profileForm.email}
                  onChange={(event) =>
                    setProfileForm((currentValue) => ({ ...currentValue, email: event.target.value }))
                  }
                  error={getFieldError(profileErrors, 'email')}
                  required
                />
                <FormField
                  label="Phone"
                  name="phone"
                  type="tel"
                  autoComplete="tel"
                  inputMode="tel"
                  value={profileForm.phone}
                  onChange={(event) =>
                    setProfileForm((currentValue) => ({ ...currentValue, phone: event.target.value }))
                  }
                  error={getFieldError(profileErrors, 'phone')}
                />
                <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSavingProfile}>
                  {isSavingProfile ? 'Saving…' : 'Save profile'}
                </button>
              </form>
            </div>
          ) : null}
        </div>

        <div className="account-accordion">
          <button
            type="button"
            className="account-accordion-trigger"
            aria-expanded={passwordOpen}
            aria-controls="account-password-panel"
            id="account-password-trigger"
            onClick={() => setPasswordOpen((open) => !open)}
          >
            <span>
              <i className={`bi ${AppIcons.password}`} aria-hidden="true"></i>
              Password
            </span>
            <i className={`bi ${passwordOpen ? AppIcons.chevronDown : AppIcons.chevronRight}`} aria-hidden="true"></i>
          </button>
          {passwordOpen ? (
            <div
              id="account-password-panel"
              role="region"
              aria-labelledby="account-password-trigger"
              className="account-accordion-panel"
            >
              <FormFeedback message={passwordMessage} variant={passwordMessageVariant} />
              <form className="auth-form account-form" onSubmit={(event) => void handlePasswordSubmit(event)}>
                <PasswordField
                  label="Current password"
                  name="current_password"
                  autoComplete="current-password"
                  value={passwordForm.current_password}
                  onChange={(event) =>
                    setPasswordForm((currentValue) => ({ ...currentValue, current_password: event.target.value }))
                  }
                  error={getFieldError(passwordErrors, 'current_password')}
                  required
                />
                <PasswordField
                  label="New password"
                  name="password"
                  autoComplete="new-password"
                  value={passwordForm.password}
                  onChange={(event) =>
                    setPasswordForm((currentValue) => ({ ...currentValue, password: event.target.value }))
                  }
                  error={getFieldError(passwordErrors, 'password')}
                  required
                />
                <PasswordField
                  label="Confirm new password"
                  name="password_confirmation"
                  autoComplete="new-password"
                  value={passwordForm.password_confirmation}
                  onChange={(event) =>
                    setPasswordForm((currentValue) => ({
                      ...currentValue,
                      password_confirmation: event.target.value,
                    }))
                  }
                  error={getFieldError(passwordErrors, 'password_confirmation')}
                  required
                />
                <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSavingPassword}>
                  {isSavingPassword ? 'Updating…' : 'Update password'}
                </button>
              </form>
            </div>
          ) : null}
        </div>
      </section>

      <section className="account-logout-block">
        <button
          type="button"
          className="btn btn-outline-dark btn-lg rounded-pill w-100"
          onClick={() => void handleLogout()}
          disabled={isLoggingOut}
        >
          {isLoggingOut ? 'Signing out…' : 'Sign out'}
        </button>
      </section>
    </div>
  );
}
