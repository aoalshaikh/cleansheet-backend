import { Injectable } from '@nestjs/common';
import { ISmsService } from '../interfaces/sms-service.interface';
import { HttpService } from '@nestjs/axios';
import { ConfigService } from '@nestjs/config';
import { lastValueFrom } from 'rxjs';

@Injectable()
export class ProviderASmsService implements ISmsService {
  private readonly providerUrl: string;
  private apiKey: string;

  constructor(
    private readonly httpService: HttpService,
    private readonly configService: ConfigService,
  ) {
    this.providerUrl = this.configService.get<string>('PROVIDER_A_URL');
    this.apiKey = this.configService.get<string>('PROVIDER_A_API_KEY');
  }

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  async sendSms(phoneNumber: string, message: string): Promise<void> {
    // Implement the API call to Provider A
    const payload = { /* Provider A specific payload */ };
    const headers = { /* Provider A specific headers */ };

    try {
      await lastValueFrom(
        this.httpService.post(this.providerUrl, payload, { headers }),
      );
    } catch (error) {
      console.error('Provider A SMS Error:', error.message);
      throw new Error('Failed to send SMS via Provider A');
    }
  }
}
