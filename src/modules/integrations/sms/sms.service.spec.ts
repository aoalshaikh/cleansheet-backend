import { Test, TestingModule } from '@nestjs/testing';
import { SmsService } from './sms.service';
import { HttpService } from '@nestjs/axios';
import { ConfigService } from '@nestjs/config';
import { of } from 'rxjs';

describe('SmsService', () => {
  let service: SmsService;
  let httpService: HttpService;
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  let configService: ConfigService;

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        SmsService,
        {
          provide: HttpService,
          useValue: {
            post: jest.fn().mockReturnValue(of({ data: {} })),
          },
        },
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn().mockImplementation((key) => {
              if (key === 'SMS_PROVIDER_URL') {
                return 'https://api.testprovider.com/send';
              }
              if (key === 'SMS_API_KEY') {
                return 'test_api_key';
              }
              return null;
            }),
          },
        },
      ],
    }).compile();

    service = module.get<SmsService>(SmsService);
    httpService = module.get<HttpService>(HttpService);
    configService = module.get<ConfigService>(ConfigService);
  });

  it('should send SMS', async () => {
    await service.sendSms('+1234567890', 'Test message');
    expect(httpService.post).toHaveBeenCalledWith(
      'https://api.testprovider.com/send',
      {
        to: '+1234567890',
        message: 'Test message',
      },
      {
        headers: {
          'Content-Type': 'application/json',
          Authorization: 'Bearer test_api_key',
        },
      },
    );
  });
});
