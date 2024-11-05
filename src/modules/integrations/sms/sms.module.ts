import { Module } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { HttpModule, HttpService } from '@nestjs/axios';
import { SmsService } from './sms.service';
import { MockSmsService } from './mock-sms.service';
import { ISmsService } from './interfaces/sms-service.interface';
import { ProviderASmsService } from './providers/provider-a-sms.service';
import { SMS_SERVICE } from './constants';

@Module({
  imports: [HttpModule],
  providers: [
    {
      provide: SMS_SERVICE,
      useFactory: (
        configService: ConfigService,
        httpService: HttpService,
      ): ISmsService => {
        const environment = configService.get<string>('NODE_ENV');
        const provider = configService.get<string>('SMS_PROVIDER');

        if (environment !== 'production') {
          return new MockSmsService();
        }

        switch (provider) {
          case 'providerA':
            return new ProviderASmsService(httpService, configService);
          case 'generic':
            return new SmsService(httpService, configService);
          default:
            throw new Error('No valid SMS provider configured');
        }
      },
      inject: [ConfigService, HttpService],
    },
  ],
  exports: [SMS_SERVICE],
})
export class SmsModule {}
