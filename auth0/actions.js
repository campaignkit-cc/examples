const fetch = require('node-fetch');

/**
 * Validate the given email address using CampaignKit.cc
 */
const validateEmail = async (event) => {
  const token = event.secrets.TOKEN;
  const { user: { email } } = event;
  const body = {
    emails: [email],
  };

  const response = await fetch('https://api.campaignkit.cc/v1/email/validate', {
    method: 'post',
    body: JSON.stringify(body),
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  });

  return response.json();
};

const errorMessage = (data) => {
  const desc = data.result.description;

  if (desc.includes('mailbox')) {
    return 'Provided email address does not exist. Please check for typos.';
  }

  if (desc.includes('blacklist')) {
    return 'Provided email address is blacklisted. Please check for typos.';
  }

  if (desc.includes('disposable')) {
    return 'Disposable email addresses are not supported. Please use a permanent email address for sign up.';
  }

  return 'Provided email address is invalid. Please check for typos.';
};

/**
* Handler that will be called during the execution of a PreUserRegistration flow.
*
* @param {Event} event - Details about the context and user that is attempting to register.
* @param {PreUserRegistrationAPI} api - Interface whose methods can be used to change the behavior of the signup.
*/
exports.onExecutePreUserRegistration = async (event, api) => {
  try {
    const response = await validateEmail(event);
    if (response.results.length < 1) {
      return;
    }

    const data = response.results[0];
    if (data.result.score < 3) {
      api.access.deny('invalid_email_address', errorMessage(data));
    }
  } catch (e) {
    // always terminate without error to not block sign ups.
  }
};
