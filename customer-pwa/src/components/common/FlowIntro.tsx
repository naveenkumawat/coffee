import { ReactNode } from 'react';
import { useNavigate } from 'react-router-dom';

interface FlowIntroProps {
  title: string;
  subtitle?: string | null;
  showBack?: boolean;
  trailing?: ReactNode;
}

/** Compact in-content header (not a decorative page-title strip). */
export function FlowIntro({ title, subtitle = null, showBack = true, trailing }: FlowIntroProps) {
  const navigate = useNavigate();

  return (
    <header className="flow-intro">
      <div className="flow-intro-main">
        {showBack ? (
          <button type="button" className="icon-button" onClick={() => navigate(-1)} aria-label="Go back">
            <i className="bi bi-arrow-left" aria-hidden="true"></i>
          </button>
        ) : null}
        <div className="flow-intro-copy">
          <h1>{title}</h1>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
      </div>
      {trailing ? <div className="flow-intro-trailing">{trailing}</div> : null}
    </header>
  );
}
