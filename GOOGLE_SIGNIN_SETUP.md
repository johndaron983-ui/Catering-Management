# Google Sign In Integration Guide

## Files Created

1. **GoogleTokenVerifierService** (`src/Service/GoogleTokenVerifierService.php`)
   - Verifies Google ID tokens using firebase/php-jwt
   - Falls back to Google's tokeninfo endpoint
   - Caches Google's public keys

2. **ApiGoogleLoginController** (`src/Controller/ApiGoogleLoginController.php`)
   - POST `/api/google-login` endpoint
   - Auto-creates users from Google token data
   - Returns JWT token for subsequent API calls

3. **Security Configuration** (Updated `config/packages/security.yaml`)
   - Added `/api/google-login` to public access routes

## Dependencies Installed

```bash
composer require google/auth
# Also installed: firebase/php-jwt v7.0.5
```

## API Endpoint

**POST** `/api/google-login`

### Request
```json
{
  "token": "google_id_token_from_client"
}
```

### Response (Success - 200)
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "user": {
    "id": 1,
    "username": "johndoe",
    "name": "John Doe",
    "email": "johndoe@gmail.com"
  }
}
```

### Response (Error - 400/401)
```json
{
  "message": "Google ID token is required."
}
```

## Testing in Postman

### Step-by-Step:

1. **Get a Valid Google ID Token**
   - From your React Native app (see below)
   - OR use [Google OAuth Playground](https://developers.google.com/oauthplayground):
     1. Click the gear icon (OAuth 2.0 Configuration)
     2. Check **Use your own OAuth credentials**
     3. Enter your `OAUTH_GOOGLE_CLIENT_ID` and `OAUTH_GOOGLE_CLIENT_SECRET` from `.env`
     4. Add redirect URI `https://developers.google.com/oauthplayground` in Google Cloud Console
     5. In Step 1, select scopes: `openid`, `email`, `profile`
     6. Authorize, then exchange the code in Step 2
     7. Copy the **`id_token`** field (NOT the `access_token`)
     8. Use the token within ~1 hour (it expires quickly)

2. **Create Postman Request**
   - Method: `POST`
   - URL: `http://localhost:8000/api/google-login`
   - Headers: `Content-Type: application/json`
   - Body (raw JSON):
   ```json
   {
     "token": "your_google_id_token_here"
   }
   ```

3. **Send & Check Response**
   - Should return 200 with JWT token
   - Save the `token` value for future API calls

4. **Use JWT Token for Protected Routes**
   - Add header: `Authorization: Bearer eyJhbGc...`
   - This token works with all protected `/api/*` endpoints

## React Native Setup

```javascript
// In your React Native app
import { GoogleSignin } from '@react-native-google-signin/google-signin';

GoogleSignin.configure({
  webClientId: 'YOUR_WEB_CLIENT_ID.apps.googleusercontent.com',
  iosClientId: 'YOUR_IOS_CLIENT_ID.apps.googleusercontent.com',
});

// Get token and send to backend
async function loginWithGoogle() {
  try {
    await GoogleSignin.hasPlayServices();
    const userInfo = await GoogleSignin.signIn();
    const idToken = userInfo.idToken;
    
    // Send to your backend
    const response = await fetch('http://your-backend.com/api/google-login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: idToken }),
    });
    
    const data = await response.json();
    // Save data.token for future API calls
    return data;
  } catch (error) {
    console.error('Google Sign In error:', error);
  }
}
```

## Features

✅ Auto-creates users from Google token  
✅ Sets user as verified (Google verified email)  
✅ Generates unique username from email  
✅ Sets random password (not used for Google auth)  
✅ Logs authentication events  
✅ Returns JWT token compatible with existing security setup  
✅ Handles errors gracefully  
✅ Verifies tokens both locally and via Google API  

## What Happens on First Login

1. User taps "Sign in with Google" in React Native app
2. Google OAuth flow authenticates user
3. ID token is sent to `/api/google-login`
4. Backend verifies token with Google
5. New user is created automatically if not exists
6. User is marked as verified (email confirmed by Google)
7. Unique username generated from email
8. JWT token returned for future API calls

## What Happens on Second Login

1. Same flow as above
2. Existing user is found and reused
3. New JWT token is generated
4. No duplicate user created

## Troubleshooting

| Issue | Solution |
|-------|----------|
| 404 Endpoint not found | Ensure Symfony is running: `symfony serve` |
| 400 Missing token | Add `"token"` field to request body |
| 401 Invalid token | Use `id_token` (not access token), fresh token (<1h), correct scopes (`openid email profile`), and your own OAuth client in Playground |
| 500 Server error | Check logs: `symfony tail -f` or `var/log/dev.log` |
| Token verification failed | Token may be expired, get a fresh one from Google |

## Running Your Symfony App

```bash
# Start development server
symfony serve

# Or with PHP
php -S localhost:8000 -t public/

# View logs
symfony tail -f

# Run tests
php bin/phpunit
```

## Database Changes

No migrations needed! The endpoint uses existing User entity structure.

## Next Steps

1. ✅ Backend is ready
2. 🚀 Test endpoint in Postman with real Google token
3. 🔗 Integrate with React Native app
4. 📱 Deploy to production with your domain
5. 🔐 Update Google OAuth credentials for production
