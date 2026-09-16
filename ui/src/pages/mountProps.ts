/**
 * What a mounted page can be told by the element it renders into.
 *
 * Its own module rather than `index.tsx`, so a component can take these props without
 * importing the file that mounts it.
 */
export type MountProps = {
  /**
   * Whose data to show, when the page is not the participant's own. The admin desk sets it on
   * the mount element; on the storefront it is absent and the API answers for the signed-in
   * user. It cannot ride in the JWT — that names the operator, who is not the customer.
   */
  customerId?: number;
};
