import { Navigate } from "react-router";

/** `/` sends a signed-in user to the dashboard and everyone else to the login screen. */
export function RootRedirect() {
  const hasToken = !!localStorage.getItem("smartpos-auth");
  return <Navigate to={hasToken ? "/dashboard" : "/login"} replace />;
}
