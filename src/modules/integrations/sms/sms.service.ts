import { Injectable } from '@nestjs/common';
import { ISmsService } from './interfaces/sms-service.interface';
import { ConfigService } from '@nestjs/config';
import { lastValueFrom } from 'rxjs';
import { HttpService } from '@nestjs/axios';

@Injectable()
export class SmsService implements ISmsService {
  private smsProviderUrl: string;
  private apiKey: string;

  constructor(
    private readonly httpService: HttpService,
    private readonly configService: ConfigService,
  ) {
    this.smsProviderUrl = this.configService.get<string>('SMS_PROVIDER_URL');
    this.apiKey = this.configService.get<string>('SMS_API_KEY');
  }

  async sendSms(phoneNumber: string, message: string): Promise<void> {
    const payload = {
      to: phoneNumber,
      message: message,
      // Additional fields as required by your SMS provider
    };

    const headers = {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${this.apiKey}`,
    };

    try {
      const response = await lastValueFrom(
        this.httpService.post(this.smsProviderUrl, payload, { headers }),
      );
      // Handle response as needed
    } catch (error) {
      // Handle error appropriately
      console.error('Error sending SMS:', error.message);
      throw new Error('Failed to send SMS');
    }
  }
}
