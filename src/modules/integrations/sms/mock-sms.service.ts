import { Injectable } from '@nestjs/common';
import { ISmsService } from './interfaces/sms-service.interface';

@Injectable()
export class MockSmsService implements ISmsService {
  async sendSms(phoneNumber: string, message: string): Promise<void> {
    console.log(`Mock SMS sent to ${phoneNumber}: ${message}`);
    // Simulate a delay if needed
  }
}
