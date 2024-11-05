export interface ISmsService {
  sendSms(phoneNumber: string, message: string): Promise<void>;
}