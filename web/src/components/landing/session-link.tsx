import { Link } from "react-router";
import { Button } from "@/components/ui/button";
import { useAuth } from "@/stores/auth";

/**
 * Nav call-to-action that knows whether someone is already signed in.
 * Renders the signed-out labels until the persisted token has loaded, so the
 * server and client markup agree on first paint.
 */
export function SessionLink() {
  const hydrated = useAuth((s) => s.hydrated);
  const token = useAuth((s) => s.token);
  const signedIn = hydrated && !!token;

  return (
    <div className="flex items-center gap-2">
      {signedIn ? (
        <Button asChild size="sm">
          <Link to="/dashboard">Open dashboard</Link>
        </Button>
      ) : (
        <>
          <Button asChild variant="ghost" size="sm">
            <Link to="/login">Sign in</Link>
          </Button>
          <Button asChild size="sm">
            <Link to="/register">Start free</Link>
          </Button>
        </>
      )}
    </div>
  );
}
