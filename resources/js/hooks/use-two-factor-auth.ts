// Two-factor authentication is currently disabled
export const OTP_MAX_LENGTH = 6;

export type UseTwoFactorAuthReturn = {
    qrCodeSvg: string | null;
    manualSetupKey: string | null;
    recoveryCodesList: string[];
    hasSetupData: boolean;
    errors: string[];
    clearErrors: () => void;
    clearSetupData: () => void;
    fetchQrCode: () => Promise<void>;
    fetchSetupKey: () => Promise<void>;
    fetchSetupData: () => Promise<void>;
    fetchRecoveryCodes: () => Promise<void>;
};

const noop = () => {};
const asyncNoop = async () => {};

export const useTwoFactorAuth = (): UseTwoFactorAuthReturn => ({
    qrCodeSvg: null,
    manualSetupKey: null,
    recoveryCodesList: [],
    hasSetupData: false,
    errors: [],
    clearErrors: noop,
    clearSetupData: noop,
    fetchQrCode: asyncNoop,
    fetchSetupKey: asyncNoop,
    fetchSetupData: asyncNoop,
    fetchRecoveryCodes: asyncNoop,
});
