import { FormEvent, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { updateCustomerPassword, updateCustomerProfile } from '../api/account';
import { ApiError, ApiValidationErrors } from '../api/client';
import { FormFeedback } from '../components/forms/FormFeedback';
import { FormField } from '../components/forms/FormField';
import { PasswordField } from '../components/forms/PasswordField';
import { LoadingSkeleton } from '../components/common/LoadingSkeleton';
import { useAuthStore } from '../stores/authStore';
import { useToastStore } from '../stores/toastStore';
import { getFieldError } from '../utils/forms';

export function AccountPage() {
  const customer = useAuthStore((state) => state.customer);
  const syncCustomer = useAuthStore((state) => state.syncCustomer);
  const logout = useAuthStore((state) => state.logout);
  const toastSuccess = useToastStore((state) => state.success);
  const navigate = useNavigate();
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
    } catch (error) {
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
    } catch (error) {
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
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Ordering</span>
            <h2>Quick access</h2>
          </div>
        </div>
        <div className="account-link-list">
          <Link to="/orders" className="account-link-row is-emphasis">
            <span>
              <i className="bi bi-receipt" aria-hidden="true"></i>
              My Orders
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/favourites" className="account-link-row is-emphasis">
            <span>
              <i className="bi bi-heart" aria-hidden="true"></i>
              Favourites
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/account/referral" className="account-link-row is-emphasis">
            <span>
              <i className="bi bi-people" aria-hidden="true"></i>
              Refer a friend
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/account/loyalty" className="account-link-row is-emphasis">
            <span>
              <i className="bi bi-star" aria-hidden="true"></i>
              Loyalty points
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/account/rewards" className="account-link-row is-emphasis">
            <span>
              <i className="bi bi-gift" aria-hidden="true"></i>
              Rewards
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/menu" className="account-link-row is-emphasis">
            <span>
              <i className="bi bi-cup-hot" aria-hidden="true"></i>
              Browse menu
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
        </div>
      </section>

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Profile</span>
            <h2>Edit details</h2>
            <p>Used for order updates and pickup contact.</p>
          </div>
        </div>
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
            onChange={(event) => setProfileForm((currentValue) => ({ ...currentValue, email: event.target.value }))}
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
            onChange={(event) => setProfileForm((currentValue) => ({ ...currentValue, phone: event.target.value }))}
            error={getFieldError(profileErrors, 'phone')}
          />
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSavingProfile}>
            {isSavingProfile ? 'Saving…' : 'Save profile'}
          </button>
        </form>
      </section>

      <section className="account-section">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Security</span>
            <h2>Change password</h2>
            <p>Enter your current password to choose a new one.</p>
          </div>
        </div>
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
            onChange={(event) => setPasswordForm((currentValue) => ({ ...currentValue, password: event.target.value }))}
            error={getFieldError(passwordErrors, 'password')}
            required
          />
          <PasswordField
            label="Confirm new password"
            name="password_confirmation"
            autoComplete="new-password"
            value={passwordForm.password_confirmation}
            onChange={(event) =>
              setPasswordForm((currentValue) => ({ ...currentValue, password_confirmation: event.target.value }))
            }
            error={getFieldError(passwordErrors, 'password_confirmation')}
            required
          />
          <button type="submit" className="btn btn-primary btn-lg rounded-pill w-100" disabled={isSavingPassword}>
            {isSavingPassword ? 'Updating…' : 'Update password'}
          </button>
        </form>
      </section>

      <section className="account-section account-shortcuts-secondary">
        <div className="account-section-heading">
          <div>
            <span className="auth-badge">Cafe</span>
            <h2>More info</h2>
          </div>
        </div>
        <div className="account-link-list is-quiet">
          <Link to="/about" className="account-link-row">
            <span>
              <i className="bi bi-info-circle" aria-hidden="true"></i>
              About
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/contact" className="account-link-row">
            <span>
              <i className="bi bi-geo-alt" aria-hidden="true"></i>
              Visit
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/faq" className="account-link-row">
            <span>
              <i className="bi bi-question-circle" aria-hidden="true"></i>
              FAQ
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/terms" className="account-link-row">
            <span>
              <i className="bi bi-file-text" aria-hidden="true"></i>
              Terms
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
          <Link to="/privacy" className="account-link-row">
            <span>
              <i className="bi bi-shield-check" aria-hidden="true"></i>
              Privacy
            </span>
            <i className="bi bi-chevron-right" aria-hidden="true"></i>
          </Link>
        </div>
      </section>

      <section className="account-logout-block">
        <button
          type="button"
          className="btn btn-outline-dark btn-lg rounded-pill w-100"
          onClick={() => void handleLogout()}
          disabled={isLoggingOut}
        >
          {isLoggingOut ? 'Signing out…' : 'Log out'}
        </button>
      </section>
    </div>
  );
}
